<?php

namespace App\Controller;

use App\Entity\DemandeConsignation;
use App\Entity\DemandeConsignationLigne;
use App\Entity\AgentCommercial;
use App\Entity\Conditionnement;
use App\Entity\Depot;
use App\Entity\Produit;
use App\Entity\Stock;
use App\Exception\OperationImpossibleException;
use App\Pagination\Page;
use App\Security\Voter\ConsignationVoter;
use App\Service\ConsignationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/consignations')]
class ConsignationController extends AbstractController
{
    #[Route('', name: 'app_consignation_index')]
    public function index(Request $request, EntityManagerInterface $em, ConsignationService $consignationService): Response
    {
        // DR, valideurs, dépôt et lignes chargés avec chaque demande
        $qb = $em->createQueryBuilder()
            ->select('c', 'dr', 'dru', 'sup', 'supu', 'asm', 'asmu', 'dep', 'l', 'p', 'cond')
            ->from(DemandeConsignation::class, 'c')
            ->leftJoin('c.dr', 'dr')
            ->leftJoin('dr.utilisateur', 'dru')
            ->leftJoin('c.superviseur', 'sup')
            ->leftJoin('sup.utilisateur', 'supu')
            ->leftJoin('c.asm', 'asm')
            ->leftJoin('asm.utilisateur', 'asmu')
            ->leftJoin('c.depot', 'dep')
            ->leftJoin('c.lignes', 'l')
            ->leftJoin('l.produit', 'p')
            ->leftJoin('l.conditionnement', 'cond')
            ->orderBy('c.dateDemande', 'DESC')
            ->addOrderBy('c.id', 'DESC');
        if ($statut = $request->query->get('statut')) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }
        $consignations = Page::depuisRequete($qb, $request, jointureCollection: true);

        $agents = $em->createQueryBuilder()
            ->select('a', 'u')->from(AgentCommercial::class, 'a')->join('a.utilisateur', 'u')
            ->where('a.actif = true')->orderBy('u.nom')
            ->getQuery()->getResult();
        $depots = $em->getRepository(Depot::class)->findBy(['actif' => true]);
        $produits = $em->getRepository(Produit::class)->findBy(['actif' => true]);
        $conditionnements = $em->getRepository(Conditionnement::class)->findAll();

        // Prix attendus pour le contrôle de caisse des tournées en cours
        $prixAttendus = [];
        foreach ($consignations as $c) {
            if ($c->getStatut() === 'VALIDEE_ASM') {
                $prixAttendus += $consignationService->prixAttendus($c);
            }
        }

        return $this->render('consignation/index.html.twig', [
            'consignations' => $consignations,
            'agents' => $agents,
            'depots' => $depots,
            'produits' => $produits,
            'conditionnements' => $conditionnements,
            'stocksDepots' => $this->getStocksDepots($em),
            'prixAttendus' => $prixAttendus,
            'agentConnecte' => $this->getAgentConnecte($em),
        ]);
    }

    #[IsGranted('ROLE_AGENT_COMMERCIAL')]
    #[Route('/nouveau', name: 'app_consignation_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        $drId = $request->request->get('dr_id');
        $depotId = $request->request->get('depot_id');
        $produitId = $request->request->get('produit_id');
        $conditionnementId = $request->request->get('conditionnement_id');
        $quantite = (float) $request->request->get('quantite', '0');
        $observation = $request->request->get('observation');

        $dr = $drId ? $em->getRepository(AgentCommercial::class)->find($drId) : null;

        // Un DR ne peut demander un prélèvement que pour lui-même
        $agentConnecte = $this->getAgentConnecte($em);
        if (!$this->isGranted('ROLE_DELEGUE') && $agentConnecte && $agentConnecte->getTypeAgent() === 'DR') {
            $dr = $agentConnecte;
        }

        $depot = $depotId ? $em->getRepository(Depot::class)->find($depotId) : null;
        $produit = $produitId ? $em->getRepository(Produit::class)->find($produitId) : null;
        $conditionnement = $conditionnementId ? $em->getRepository(Conditionnement::class)->find($conditionnementId) : null;

        if (!$dr || !$depot || !$produit || !$conditionnement || $quantite <= 0) {
            $this->addFlash('danger', 'Veuillez remplir les informations obligatoires (*) pour le prélèvement.');
            return $this->redirectToRoute('app_consignation_index');
        }
        if ($conditionnement->getProduit() !== $produit) {
            $this->addFlash('danger', 'Le conditionnement choisi ne correspond pas au produit.');
            return $this->redirectToRoute('app_consignation_index');
        }

        $c = new DemandeConsignation();
        $c->setNumero('CONS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
        $c->setDr($dr);
        $c->setDepot($depot);
        $c->setDateDemande(new \DateTime());
        $c->setStatut('SOUMISE');
        $c->setObservation($observation);

        $ligne = new DemandeConsignationLigne();
        $ligne->setDemande($c);
        $ligne->setProduit($produit);
        $ligne->setConditionnement($conditionnement);
        $ligne->setQuantiteDemandee((string) $quantite);
        $ligne->setQuantiteAccordee((string) $quantite);
        $em->persist($ligne);

        $em->persist($c);
        $em->flush();

        $this->addFlash('success', 'Demande de prélèvement ' . $c->getNumero() . ' créée par le DR ' . ($dr->getUtilisateur() ? $dr->getUtilisateur()->getNomComplet() : '') . ' (En attente validation Superviseur).');
        return $this->redirectToRoute('app_consignation_index');
    }

    #[Route('/{id}/valider-superviseur', name: 'app_consignation_valider_superviseur', methods: ['POST'])]
    public function validerSuperviseur(DemandeConsignation $c, EntityManagerInterface $em, ConsignationService $service): Response
    {
        if (!$this->isGranted(ConsignationVoter::VALIDER_SUPERVISEUR, $c)) {
            $this->addFlash('danger', 'Validation impossible : seul le Superviseur du DR (ou un ASM) peut approuver la demande ' . $c->getNumero() . ' tant qu\'elle est au statut SOUMISE.');
            return $this->redirectToRoute('app_consignation_index');
        }

        return $this->executer(function () use ($c, $em, $service) {
            $service->validerSuperviseur($c, $this->getAgentConnecte($em));
            $this->addFlash('info', 'Demande ' . $c->getNumero() . ' approuvée par le Superviseur des Ventes (En attente validation finale ASM).');
        });
    }

    #[Route('/{id}/valider-asm', name: 'app_consignation_valider_asm', methods: ['POST'])]
    public function validerAsm(DemandeConsignation $c, EntityManagerInterface $em, ConsignationService $service): Response
    {
        if (!$this->isGranted(ConsignationVoter::VALIDER_ASM, $c)) {
            $this->addFlash('danger', 'Validation impossible : la demande ' . $c->getNumero() . ' doit d\'abord être approuvée par le Superviseur, puis validée par un ASM différent.');
            return $this->redirectToRoute('app_consignation_index');
        }

        return $this->executer(function () use ($c, $em, $service) {
            $service->validerAsm($c, $this->getAgentConnecte($em), $this->getUser());
            $this->addFlash('success', 'Prélèvement ' . $c->getNumero() . ' validé par l\'ASM ! Stock sorti du dépôt et placé sur le stock du DR pour sa tournée.');
        }, 'Validation ASM refusée, aucun stock n\'a été sorti : ');
    }

    #[Route('/{id}/rejeter', name: 'app_consignation_rejeter', methods: ['POST'])]
    public function rejeter(DemandeConsignation $c, Request $request, ConsignationService $service): Response
    {
        if (!$this->isGranted(ConsignationVoter::REJETER, $c)) {
            $this->addFlash('danger', 'Vous n\'êtes pas habilité à rejeter la demande ' . $c->getNumero() . '.');
            return $this->redirectToRoute('app_consignation_index');
        }

        $motif = trim((string) $request->request->get('motif'));
        if ($motif === '') {
            $this->addFlash('danger', 'Indiquez le motif du rejet.');
            return $this->redirectToRoute('app_consignation_index');
        }

        return $this->executer(function () use ($c, $motif, $service) {
            $service->rejeter($c, $motif);
            $this->addFlash('warning', 'Demande ' . $c->getNumero() . ' rejetée.');
        });
    }

    #[Route('/{id}/cloturer', name: 'app_consignation_cloturer', methods: ['POST'])]
    public function cloturer(DemandeConsignation $c, Request $request, ConsignationService $service): Response
    {
        if (!$this->isGranted(ConsignationVoter::CLOTURER, $c)) {
            $this->addFlash('danger', 'Clôture impossible : la demande ' . $c->getNumero() . ' doit être validée par l\'ASM, et seuls le DR, sa hiérarchie ou le gestionnaire de dépôt peuvent la clôturer.');
            return $this->redirectToRoute('app_consignation_index');
        }

        $quantites = [];
        foreach ($c->getLignes() as $ligne) {
            $quantites[$ligne->getId()] = [
                'vendue' => (float) $request->request->get('quantiteVendue_' . $ligne->getId(), '0'),
                'retournee' => (float) $request->request->get('quantiteRetournee_' . $ligne->getId(), '0'),
            ];
        }
        $cash = (float) $request->request->get('cashJustifie', '0');

        return $this->executer(function () use ($c, $cash, $quantites, $request, $service) {
            $res = $service->cloturer($c, $cash, $quantites, $request->request->get('justificationEcart'), $this->getUser());
            $message = 'Clôture de journée enregistrée pour le DR ! Cash justifié : ' . number_format($cash, 2, ',', ' ') . ' $, invendus réintégrés au dépôt.';
            if ($res['ecart'] !== null && abs($res['ecart']) >= 0.01) {
                $message .= ' Écart de caisse justifié : ' . number_format($res['ecart'], 2, ',', ' ') . ' $.';
            }
            $this->addFlash('success', $message);
        }, 'Clôture refusée : ');
    }

    /** Exécute une opération métier et transforme un refus en message utilisateur. */
    private function executer(callable $operation, string $prefixe = ''): Response
    {
        try {
            $operation();
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', $prefixe . $e->getMessage());
        }

        return $this->redirectToRoute('app_consignation_index');
    }

    /**
     * Stock disponible par dépôt et conditionnement, pour guider la saisie d'une demande.
     *
     * @return array<int, array<int, float>> depotId => [conditionnementId => quantité]
     */
    private function getStocksDepots(EntityManagerInterface $em): array
    {
        $rows = $em->createQueryBuilder()
            ->select('IDENTITY(e.depot) AS depot', 'IDENTITY(s.conditionnement) AS cond', 'SUM(s.quantiteDisponible) AS qte')
            ->from(Stock::class, 's')
            ->join('s.emplacement', 'e')
            ->where("e.typeEmplacement = 'DEPOT' AND e.depot IS NOT NULL AND s.conditionnement IS NOT NULL")
            ->groupBy('e.depot', 's.conditionnement')
            ->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['depot']][(int) $r['cond']] = (float) $r['qte'];
        }

        return $map;
    }

    private function getAgentConnecte(EntityManagerInterface $em): ?AgentCommercial
    {
        return $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $this->getUser(), 'actif' => true]);
    }
}
