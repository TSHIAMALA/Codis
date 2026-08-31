<?php

namespace App\Controller;

use App\Entity\Grossiste;
use App\Entity\Delegue;
use App\Entity\AgentCommercial;
use App\Entity\Detailant;
use App\Entity\Utilisateur;
use App\Entity\Pays;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/commercial')]
class CommercialController extends AbstractController
{
    #[Route('', name: 'app_commercial_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $activeTab = $request->query->get('tab', 'grossistes');
        $grossistes = $em->getRepository(Grossiste::class)->findBy(['statut' => ['ACTIF', 'EN_ATTENTE', 'SUSPENDU']]);
        $delegues = $em->getRepository(Delegue::class)->findAll();
        $agents = $em->getRepository(AgentCommercial::class)->findAll();
        $detailants = $em->getRepository(Detailant::class)->findBy(['statut' => ['ACTIF', 'EN_ATTENTE', 'SUSPENDU']], ['nom' => 'ASC']);
        $utilisateurs = $em->getRepository(Utilisateur::class)->findBy(['actif' => true]);
        $paysList = $em->getRepository(Pays::class)->findBy(['actif' => true]);

        return $this->render('commercial/index.html.twig', [
            'grossistes' => $grossistes,
            'delegues' => $delegues,
            'agents' => $agents,
            'detailants' => $detailants,
            'utilisateurs' => $utilisateurs,
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

        $paysId = $request->request->get('pays_id');
        if ($paysId) {
            $pays = $em->getRepository(Pays::class)->find($paysId);
            if ($pays) $d->setPays($pays);
        } else {
            $pays = $em->getRepository(Pays::class)->findOneBy(['actif' => true]);
            if ($pays) $d->setPays($pays);
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

        $em->flush();
        $this->addFlash('info', 'Détaillant ' . $d->getNom() . ' mis à jour.');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'detailants']);
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
        $utilisateurId = $request->request->get('utilisateur_id');
        $utilisateur = null;

        if ($utilisateurId) {
            $utilisateur = $em->getRepository(Utilisateur::class)->find($utilisateurId);
        } else {
            // Create user profile on the fly
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $username = trim($request->request->get('username', ''));
            $plainPassword = $request->request->get('password', 'agent123');

            if (!empty($username) && !empty($nom)) {
                $utilisateur = new Utilisateur();
                $utilisateur->setNom($nom);
                $utilisateur->setPrenom($prenom);
                $utilisateur->setUsername($username);
                $utilisateur->setEmail($request->request->get('email') ?: null);
                $utilisateur->setTelephone($request->request->get('telephone') ?: null);
                $utilisateur->setActif(true);

                $hashedPassword = $hasher->hashPassword($utilisateur, $plainPassword);
                $utilisateur->setPassword($hashedPassword);

                $roleComm = $em->getRepository(\App\Entity\Role::class)->findOneBy(['code' => 'COMMERCIAL']);
                if ($roleComm) $utilisateur->addRoleEntity($roleComm);

                $em->persist($utilisateur);
            }
        }

        if (!$utilisateur) {
            $this->addFlash('danger', 'Veuillez remplir les informations du nouvel agent commercial (*).');
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }

        $existingAgent = $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $utilisateur]);
        if ($existingAgent) {
            $this->addFlash('warning', 'Cet utilisateur est déjà configuré comme Agent Commercial.');
            return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
        }

        $agent = new AgentCommercial();
        $agent->setUtilisateur($utilisateur);
        $agent->setActif(true);

        $paysId = $request->request->get('pays_id');
        if ($paysId) {
            $pays = $em->getRepository(Pays::class)->find($paysId);
            if ($pays) $agent->setPays($pays);
        } else {
            $pays = $em->getRepository(Pays::class)->findOneBy(['actif' => true]);
            if ($pays) $agent->setPays($pays);
        }

        $delegueId = $request->request->get('delegue_id');
        if ($delegueId) {
            $delegue = $em->getRepository(Delegue::class)->find($delegueId);
            if ($delegue) $agent->setDelegue($delegue);
        }

        $em->persist($agent);
        $em->flush();

        $this->addFlash('success', 'Agent commercial ' . $utilisateur->getNomComplet() . ' créé avec succès !');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
    }

    #[Route('/agent/{id}/modifier', name: 'app_agent_modifier', methods: ['POST'])]
    public function modifierAgent(AgentCommercial $agent, Request $request, EntityManagerInterface $em): Response
    {
        $delegueId = $request->request->get('delegue_id');
        if ($delegueId) {
            $delegue = $em->getRepository(Delegue::class)->find($delegueId);
            $agent->setDelegue($delegue);
        } else {
            $agent->setDelegue(null);
        }

        $paysId = $request->request->get('pays_id');
        if ($paysId) {
            $pays = $em->getRepository(Pays::class)->find($paysId);
            if ($pays) $agent->setPays($pays);
        }

        $agent->setActif($request->request->get('actif') === '1');
        $em->flush();

        $this->addFlash('info', 'Profil de l\'agent commercial ' . ($agent->getUtilisateur() ? $agent->getUtilisateur()->getNomComplet() : '') . ' mis à jour.');
        return $this->redirectToRoute('app_commercial_index', ['tab' => 'agents']);
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