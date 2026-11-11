<?php

namespace App\Controller;

use App\Entity\Grossiste;
use App\Entity\Delegue;
use App\Entity\AgentCommercial;
use App\Entity\Detailant;
use App\Entity\Utilisateur;
use App\Entity\Pays;
use App\Entity\Region;
use App\Entity\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_DELEGUE', message: 'La gestion du réseau commercial est réservée aux délégués et administrateurs.')]
#[Route('/commercial')]
class CommercialController extends AbstractController
{
    #[Route('', name: 'app_commercial_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $activeTab = $request->query->get('tab', 'grossistes');
        $grossistes = $em->getRepository(Grossiste::class)->findBy(['statut' => ['ACTIF', 'EN_ATTENTE', 'SUSPENDU']]);
        $delegues = $em->getRepository(Delegue::class)->findAll();
        $agents = $em->createQueryBuilder()
            ->select('a', 'u', 'sup', 'supu', 'r', 'p')
            ->from(AgentCommercial::class, 'a')
            ->join('a.utilisateur', 'u')
            ->leftJoin('a.superviseur', 'sup')
            ->leftJoin('sup.utilisateur', 'supu')
            ->leftJoin('a.region', 'r')
            ->leftJoin('a.pays', 'p')
            ->orderBy('u.nom')
            ->getQuery()->getResult();
        // Ordre d'affichage : ASM, Superviseurs, DR, Cyclos
        usort($agents, fn (AgentCommercial $x, AgentCommercial $y) => array_search($x->getTypeAgent(), AgentCommercial::TYPES, true) <=> array_search($y->getTypeAgent(), AgentCommercial::TYPES, true));
        $comptesSansProfil = $em->createQueryBuilder()
            ->select('u')
            ->from(Utilisateur::class, 'u')
            ->where('u.actif = true')
            ->andWhere('NOT EXISTS (SELECT a2.id FROM ' . AgentCommercial::class . ' a2 WHERE a2.utilisateur = u)')
            ->orderBy('u.nom')
            ->getQuery()->getResult();
        $regions = $em->getRepository(Region::class)->findBy(['actif' => true], ['nom' => 'ASC']);
        $detailants = $em->getRepository(Detailant::class)->findBy(['statut' => ['ACTIF', 'EN_ATTENTE', 'SUSPENDU']], ['nom' => 'ASC']);
        $utilisateurs = $em->getRepository(Utilisateur::class)->findBy(['actif' => true]);
        $paysList = $em->getRepository(Pays::class)->findBy(['actif' => true]);

        return $this->render('commercial/index.html.twig', [
            'grossistes' => $grossistes,
            'delegues' => $delegues,
            'agents' => $agents,
            'detailants' => $detailants,
            'utilisateurs' => $utilisateurs,
            'comptesSansProfil' => $comptesSansProfil,
            'regions' => $regions,
            'typesAgent' => AgentCommercial::TYPES,
            'paysList' => $paysList,
            'activeTab' => $activeTab,
        ]);
    }

    // --- GROSSISTE CRUD ---
    #[Route('/grossiste/nouveau', name: 'app_grossiste_nouveau', methods: ['POST'])]
    public function nouveauGrossiste(Request $request, EntityManagerInterface $em): Response
    {
        $g = new Grossiste();
        $g->setCode('GROSS-' . strtoupper(substr(uniqid(), -5)));
        $g->setRaisonSociale($request->request->get('raisonSociale', ''));
        $g->setNomResponsable($request->request->get('nomResponsable'));
        $g->setTelephone($request->request->get('telephone'));
        $g->setEmail($request->request->get('email'));
        $g->setAdresse($request->request->get('adresse'));
        $g->setLimiteCredit($request->request->get('limiteCredit', '0.00'));
        $g->setStatut('ACTIF');
        $this->appliquerDistribution($g, $request, $em);

        $paysId = $request->request->get('pays_id');
        if ($paysId) {
            $pays = $em->getRepository(Pays::class)->find($paysId);
            if ($pays) $g->setPays($pays);
        } else {
            $pays = $em->getRepository(Pays::class)->findOneBy(['actif' => true]);
            if ($pays) $g->setPays($pays);
        }

        $em->persist($g);
        $em->flush();

        $this->addFlash('success', 'Grossiste ' . $g->getRaisonSociale() . ' créé avec succès !');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'grossistes']);
    }

    #[Route('/grossiste/{id}/modifier', name: 'app_grossiste_modifier', methods: ['POST'])]
    public function modifierGrossiste(Grossiste $g, Request $request, EntityManagerInterface $em): Response
    {
        $g->setRaisonSociale($request->request->get('raisonSociale', $g->getRaisonSociale()));
        $g->setNomResponsable($request->request->get('nomResponsable', $g->getNomResponsable()));
        $g->setTelephone($request->request->get('telephone', $g->getTelephone()));
        $g->setEmail($request->request->get('email', $g->getEmail()));
        $g->setAdresse($request->request->get('adresse', $g->getAdresse()));
        $g->setLimiteCredit($request->request->get('limiteCredit', $g->getLimiteCredit()));
        $g->setStatut($request->request->get('statut', $g->getStatut()));
        $this->appliquerDistribution($g, $request, $em);

        $em->flush();
        $this->addFlash('info', 'Grossiste ' . $g->getRaisonSociale() . ' mis à jour.');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'grossistes']);
    }

    #[Route('/grossiste/{id}/supprimer', name: 'app_grossiste_supprimer', methods: ['POST'])]
    public function supprimerGrossiste(Grossiste $g, EntityManagerInterface $em): Response
    {
        $g->setStatut('INACTIF');
        $em->flush();

        $this->addFlash('warning', 'Grossiste ' . $g->getRaisonSociale() . ' désactivé (suppression logique).');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'grossistes']);
    }

    /** Type de distribution (agréé = paiement avant livraison, secteur = vente à crédit plafonnée) et DR du secteur. */
    private function appliquerDistribution(Grossiste $g, Request $request, EntityManagerInterface $em): void
    {
        $type = $request->request->get('typeDistribution', $g->getTypeDistribution());
        if (in_array($type, ['DISTRIBUTEUR_AGREE', 'GROSSISTE_SECTEUR'], true)) {
            $g->setTypeDistribution($type);
        }
        if ($request->request->has('dr_id')) {
            $drId = $request->request->get('dr_id');
            $dr = $drId ? $em->getRepository(AgentCommercial::class)->find($drId) : null;
            $g->setDr($dr && $dr->getTypeAgent() === 'DR' ? $dr : null);
        }
    }

    // --- DETAILANT CRUD ---
    #[Route('/detailant/nouveau', name: 'app_detailant_nouveau', methods: ['POST'])]
    public function nouveauDetailant(Request $request, EntityManagerInterface $em): Response
    {
        $d = new Detailant();
        $d->setCode('DET-' . strtoupper(substr(uniqid(), -5)));
        $d->setNom($request->request->get('nom', ''));
        $d->setNomResponsable($request->request->get('nomResponsable'));
        $d->setTelephone($request->request->get('telephone'));
        $d->setEmail($request->request->get('email'));
        $d->setAdresse($request->request->get('adresse'));
        $d->setStatut('ACTIF');

        if ($erreur = $this->appliquerLocalisationDetailant($d, $request, $em)) {
            $this->addFlash('danger', $erreur);
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'detailants']);
        }

        $em->persist($d);
        $em->flush();

        $this->addFlash('success', 'Détaillant ' . $d->getNom() . ' créé avec succès !');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'detailants']);
    }

    #[Route('/detailant/{id}/modifier', name: 'app_detailant_modifier', methods: ['POST'])]
    public function modifierDetailant(Detailant $d, Request $request, EntityManagerInterface $em): Response
    {
        $d->setNom($request->request->get('nom', $d->getNom()));
        $d->setNomResponsable($request->request->get('nomResponsable', $d->getNomResponsable()));
        $d->setTelephone($request->request->get('telephone', $d->getTelephone()));
        $d->setEmail($request->request->get('email', $d->getEmail()));
        $d->setAdresse($request->request->get('adresse', $d->getAdresse()));
        $d->setStatut($request->request->get('statut', $d->getStatut()));

        if ($erreur = $this->appliquerLocalisationDetailant($d, $request, $em)) {
            $em->clear();
            $this->addFlash('danger', $erreur);
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'detailants']);
        }

        $em->flush();
        $this->addFlash('info', 'Détaillant ' . $d->getNom() . ' mis à jour.');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'detailants']);
    }

    /**
     * Pays et région du détaillant (obligatoires) ; la région doit appartenir au pays choisi.
     *
     * @return string|null message d'erreur, null si tout est valide
     */
    private function appliquerLocalisationDetailant(Detailant $d, Request $request, EntityManagerInterface $em): ?string
    {
        $paysId = $request->request->get('pays_id');
        $regionId = $request->request->get('region_id');
        $pays = $paysId ? $em->getRepository(Pays::class)->find($paysId) : null;
        $region = $regionId ? $em->getRepository(Region::class)->find($regionId) : null;

        if (!$pays) {
            return 'Veuillez choisir le pays du détaillant.';
        }
        if (!$region) {
            return 'Veuillez choisir la région du détaillant.';
        }
        if ($region->getPays() !== $pays) {
            return 'La région ' . $region->getNom() . ' n\'appartient pas au pays ' . $pays->getNom() . '.';
        }

        $d->setPays($pays);
        $d->setRegion($region);

        return null;
    }

    #[Route('/detailant/{id}/supprimer', name: 'app_detailant_supprimer', methods: ['POST'])]
    public function supprimerDetailant(Detailant $d, EntityManagerInterface $em): Response
    {
        $d->setStatut('INACTIF');
        $em->flush();

        $this->addFlash('warning', 'Détaillant ' . $d->getNom() . ' désactivé (suppression logique).');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'detailants']);
    }

    // --- AGENT COMMERCIAL CRUD ---
    #[Route('/agent/nouveau', name: 'app_agent_nouveau', methods: ['POST'])]
    public function nouveauAgent(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $typeAgent = (string) $request->request->get('typeAgent');
        if (!in_array($typeAgent, AgentCommercial::TYPES, true)) {
            $this->addFlash('danger', 'Choisissez la fonction de l\'agent (ASM, Superviseur, DR ou Cyclo).');
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }

        // Compte existant, ou compte créé avec l'agent
        $utilisateurId = $request->request->get('utilisateur_id');
        if ($utilisateurId) {
            $utilisateur = $em->getRepository(Utilisateur::class)->find($utilisateurId);
            if (!$utilisateur) {
                $this->addFlash('danger', 'Compte utilisateur introuvable.');
                return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
            }
        } else {
            $nom = trim((string) $request->request->get('nom'));
            $username = trim((string) $request->request->get('username'));
            $plainPassword = (string) $request->request->get('password');
            $email = trim((string) $request->request->get('email'));

            if ($nom === '' || $username === '' || $plainPassword === '') {
                $this->addFlash('danger', 'Choisissez un compte existant, ou renseignez le nom, l\'identifiant et le mot de passe du nouvel agent (*).');
                return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
            }
            if (mb_strlen($plainPassword) < UtilisateurController::MIN_PASSWORD_LENGTH) {
                $this->addFlash('danger', 'Le mot de passe doit contenir au moins ' . UtilisateurController::MIN_PASSWORD_LENGTH . ' caractères.');
                return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
            }
            if ($em->getRepository(Utilisateur::class)->findOneBy(['username' => $username])) {
                $this->addFlash('danger', 'L\'identifiant "' . $username . '" est déjà utilisé.');
                return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
            }
            if ($email !== '' && $em->getRepository(Utilisateur::class)->findOneBy(['email' => $email])) {
                $this->addFlash('danger', 'L\'adresse email "' . $email . '" est déjà associée à un compte.');
                return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
            }

            $utilisateur = new Utilisateur();
            $utilisateur->setNom($nom);
            $utilisateur->setPrenom(trim((string) $request->request->get('prenom')) ?: null);
            $utilisateur->setUsername($username);
            $utilisateur->setEmail($email ?: null);
            $utilisateur->setTelephone($request->request->get('telephone') ?: null);
            $utilisateur->setActif(true);
            $utilisateur->setPassword($hasher->hashPassword($utilisateur, $plainPassword));
            $em->persist($utilisateur);
        }

        if ($utilisateur->getId() && $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $utilisateur])) {
            $this->addFlash('warning', 'Ce compte a déjà un profil d\'agent commercial.');
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }

        $agent = new AgentCommercial();
        $agent->setUtilisateur($utilisateur);
        $agent->setActif(true);
        $erreur = $this->appliquerProfilAgent($agent, $typeAgent, $request, $em);
        if ($erreur) {
            $this->addFlash('danger', $erreur);
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }
        $this->donnerRoleAgent($utilisateur, $em);

        $em->persist($agent);
        $em->flush();

        $this->addFlash('success', $agent->getTypeAgentLibelle() . ' ' . $utilisateur->getNomComplet() . ' créé(e) avec succès'
            . ($agent->getSuperviseur() ? ', rattaché(e) à ' . $agent->getSuperviseur()->getUtilisateur()->getNomComplet() : '') . '.');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
    }

    #[Route('/agent/{id}/modifier', name: 'app_agent_modifier', methods: ['POST'])]
    public function modifierAgent(AgentCommercial $agent, Request $request, EntityManagerInterface $em): Response
    {
        $typeAgent = (string) $request->request->get('typeAgent', $agent->getTypeAgent());
        if (!in_array($typeAgent, AgentCommercial::TYPES, true)) {
            $this->addFlash('danger', 'Fonction d\'agent invalide.');
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }

        // Un agent qui encadre d'autres agents ne peut pas changer de niveau : il faut d'abord les rattacher ailleurs
        if ($typeAgent !== $agent->getTypeAgent()) {
            $subordonnes = $em->getRepository(AgentCommercial::class)->count(['superviseur' => $agent, 'actif' => true]);
            if ($subordonnes > 0) {
                $this->addFlash('danger', 'Impossible de changer la fonction : ' . $subordonnes . ' agent(s) actif(s) sont rattachés à cette personne. Rattachez-les d\'abord à un autre responsable.');
                return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
            }
        }

        $erreur = $this->appliquerProfilAgent($agent, $typeAgent, $request, $em);
        if ($erreur) {
            $this->addFlash('danger', $erreur);
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }
        $agent->setActif($request->request->get('actif') === '1');
        $em->flush();

        $this->addFlash('info', 'Profil de l\'agent commercial ' . ($agent->getUtilisateur() ? $agent->getUtilisateur()->getNomComplet() : '') . ' mis à jour.');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
    }

    /**
     * Fonction, supérieur hiérarchique, délégué, pays et région de l'agent.
     * Le supérieur doit avoir la fonction du niveau au-dessus (DR → Superviseur → ASM ; Cyclo → DR).
     *
     * @return string|null message d'erreur, null si tout est valide
     */
    private function appliquerProfilAgent(AgentCommercial $agent, string $typeAgent, Request $request, EntityManagerInterface $em): ?string
    {
        $typeSuperieur = AgentCommercial::typeSuperieur($typeAgent);
        $superieurId = $request->request->get('superviseur_id');
        $superieur = $superieurId ? $em->getRepository(AgentCommercial::class)->find($superieurId) : null;

        if ($superieurId && !$superieur) {
            return 'Supérieur hiérarchique introuvable.';
        }
        if ($superieur && ($superieur === $agent || $superieur->getTypeAgent() !== $typeSuperieur || !$superieur->isActif())) {
            return $typeSuperieur
                ? 'Le supérieur d\'un ' . $typeAgent . ' doit être un ' . $typeSuperieur . ' actif.'
                : 'Un ASM n\'a pas de supérieur dans le réseau commercial.';
        }

        $agent->setTypeAgent($typeAgent);
        $agent->setSuperviseur($typeSuperieur ? $superieur : null);

        $delegueId = $request->request->get('delegue_id');
        $agent->setDelegue($delegueId ? $em->getRepository(Delegue::class)->find($delegueId) : null);

        $paysId = $request->request->get('pays_id');
        $pays = $paysId ? $em->getRepository(Pays::class)->find($paysId) : null;
        $pays ??= $agent->getPays() ?? $em->getRepository(Pays::class)->findOneBy(['actif' => true]);
        $agent->setPays($pays);

        $regionId = $request->request->get('region_id');
        $agent->setRegion($regionId ? $em->getRepository(Region::class)->find($regionId) : null);

        return null;
    }

    /** Un agent doit pouvoir accéder aux écrans commerciaux : rôle Agent commercial s'il n'a aucun rôle interne. */
    private function donnerRoleAgent(Utilisateur $utilisateur, EntityManagerInterface $em): void
    {
        if (array_intersect($utilisateur->getRoles(), ['ROLE_AGENT_COMMERCIAL', 'ROLE_DELEGUE', 'ROLE_ADMIN_PAYS', 'ROLE_SUPER_ADMIN'])) {
            return;
        }
        $role = $em->getRepository(Role::class)->findOneBy(['code' => 'AGENT_COMMERCIAL']);
        if ($role) {
            $utilisateur->addRoleEntity($role);
        }
        $utilisateur->setActif(true);
    }

    #[Route('/agent/{id}/supprimer', name: 'app_agent_supprimer', methods: ['POST'])]
    public function supprimerAgent(AgentCommercial $agent, EntityManagerInterface $em): Response
    {
        $agent->setActif(false);
        $em->flush();

        $this->addFlash('warning', 'Agent commercial désactivé (suppression logique).');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
    }
}