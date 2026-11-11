<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Entity\Depot;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Entity\AgentCommercial;
use App\Exception\OperationImpossibleException;
use App\Pagination\Page;
use App\Service\OrderManagerService;
use App\Service\TarifResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_AGENT_COMMERCIAL', message: 'Les commandes sont réservées aux équipes commerciales.')]
#[Route('/commandes')]
class CommandeController extends AbstractController
{
    #[Route('', name: 'app_commande_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $qb = $em->createQueryBuilder()
            ->select('c', 'g', 'd')
            ->from(Commande::class, 'c')
            ->leftJoin('c.grossiste', 'g')
            ->leftJoin('c.detailant', 'd')
            ->orderBy('c.dateCommande', 'DESC')
            ->addOrderBy('c.id', 'DESC');
        $recherche = trim((string) $request->query->get('q'));
        if ($recherche !== '') {
            $qb->andWhere('c.numero LIKE :q OR g.raisonSociale LIKE :q OR d.nom LIKE :q')->setParameter('q', '%' . $recherche . '%');
        }
        if ($statut = $request->query->get('statut')) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }
        $commandes = Page::depuisRequete($qb, $request);
        $grossistes = $em->getRepository(Grossiste::class)->findBy(['statut' => 'ACTIF']);
        $detailants = $em->getRepository(Detailant::class)->findBy(['statut' => 'ACTIF']);
        $depots = $em->getRepository(Depot::class)->findBy(['actif' => true]);
        $produits = $em->getRepository(Produit::class)->findBy(['actif' => true]);
        $conditionnements = $em->getRepository(Conditionnement::class)->findAll();

        return $this->render('commandes/index.html.twig', [
            'commandes' => $commandes,
            'grossistes' => $grossistes,
            'detailants' => $detailants,
            'depots' => $depots,
            'produits' => $produits,
            'conditionnements' => $conditionnements,
        ]);
    }

    #[Route('/nouveau', name: 'app_commande_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, TarifResolver $tarifResolver): Response
    {
        $typeCommande = $request->request->get('typeCommande', 'GROSSISTE');
        $grossisteId = $request->request->get('grossiste_id');
        $detailantId = $request->request->get('detailant_id');
        $depotId = $request->request->get('depot_id');
        $observation = $request->request->get('observation');

        // Lignes de commande (arrays)
        $produitIds = $request->request->all('produit_ids');
        $conditionnementIds = $request->request->all('conditionnement_ids');
        $quantites = $request->request->all('quantites');

        if (empty($produitIds) || count($produitIds) === 0) {
            $this->addFlash('danger', 'Veuillez ajouter au moins une ligne de produit à la commande.');
            return $this->redirectToRoute('app_commande_index');
        }

        if (!in_array($typeCommande, ['GROSSISTE', 'DETAILLANT'], true)
            || ($typeCommande === 'GROSSISTE' && !$grossisteId)
            || ($typeCommande === 'DETAILLANT' && !$detailantId)) {
            $this->addFlash('danger', 'Veuillez sélectionner le client de la commande.');
            return $this->redirectToRoute('app_commande_index');
        }

        $commande = new Commande();
        $commande->setNumero('CMD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
        $commande->setTypeCommande($typeCommande);
        $commande->setDateCommande(new \DateTime());
        $commande->setStatut('SOUMISE');
        $commande->setObservation($observation);
        $commande->setCreatedBy($this->getUser());

        if ($typeCommande === 'GROSSISTE' && $grossisteId) {
            $grossiste = $em->getRepository(Grossiste::class)->find($grossisteId);
            $commande->setGrossiste($grossiste);
        } elseif ($typeCommande === 'DETAILLANT' && $detailantId) {
            $detailant = $em->getRepository(Detailant::class)->find($detailantId);
            $commande->setDetailant($detailant);
        }

        if ($depotId) {
            $depot = $em->getRepository(Depot::class)->find($depotId);
            $commande->setDepot($depot);
        }

        $agent = $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $this->getUser(), 'actif' => true]);
        if ($agent) {
            $commande->setAgent($agent);
        }

        $montantTotal = 0.0;
        $lignesSansTarif = [];

        for ($i = 0; $i < count($produitIds); $i++) {
            $produit = $em->getRepository(Produit::class)->find($produitIds[$i]);
            $conditionnement = isset($conditionnementIds[$i]) ? $em->getRepository(Conditionnement::class)->find($conditionnementIds[$i]) : null;
            $qte = isset($quantites[$i]) ? (float) $quantites[$i] : 0;

            if (!$produit || !$conditionnement || $qte <= 0) {
                continue;
            }

            // Le prix est toujours déterminé par la grille tarifaire, jamais par le formulaire
            $tarif = $tarifResolver->resoudre($produit, $conditionnement, $typeCommande, $commande->getGrossiste(), $commande->getDetailant());
            if (!$tarif || $tarif['prix'] <= 0) {
                $lignesSansTarif[] = $produit->getNom() . ' (' . $conditionnement->getLibelle() . ')';
                continue;
            }
            $prixU = $tarif['prix'];

            $montantLigne = $qte * $prixU;

            $ligne = new CommandeLigne();
            $ligne->setCommande($commande);
            $ligne->setProduit($produit);
            $ligne->setConditionnement($conditionnement);
            $ligne->setQuantite((string) $qte);
            $ligne->setPrixUnitaire(number_format($prixU, 2, '.', ''));
            $ligne->setMontant(number_format($montantLigne, 2, '.', ''));

            $em->persist($ligne);
            $montantTotal += $montantLigne;
        }

        // Rien n'est enregistré tant qu'il manque un tarif : aucun flush n'a eu lieu
        if ($lignesSansTarif) {
            $this->addFlash('danger', 'Commande non enregistrée : aucun tarif actif pour ' . implode(', ', $lignesSansTarif) . '. Demandez à un administrateur de compléter la grille tarifaire.');
            return $this->redirectToRoute('app_commande_index');
        }
        if ($montantTotal <= 0) {
            $this->addFlash('danger', 'Commande non enregistrée : aucune ligne valide (produit, conditionnement et quantité sont obligatoires).');
            return $this->redirectToRoute('app_commande_index');
        }

        $commande->setMontantTotal(number_format($montantTotal, 2, '.', ''));

        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Commande ' . $commande->getNumero() . ' créée avec succès pour ' . $commande->getClientNom() . ' — Montant : ' . number_format($montantTotal, 2, ',', ' ') . ' $.');
        return $this->redirectToRoute('app_commande_index');
    }

    #[Route('/{id}', name: 'app_commande_show', requirements: ['id' => '\d+'])]
    public function show(Commande $commande, OrderManagerService $orderService): Response
    {
        $credit = null;
        if ($commande->getGrossiste() && $commande->getGrossiste()->getTypeDistribution() !== 'DISTRIBUTEUR_AGREE' && in_array($commande->getStatut(), ['SOUMISE', 'BROUILLON'], true)) {
            $credit = $orderService->verifierLimiteCredit($commande->getGrossiste(), (float) $commande->getMontantTotal(), $commande);
        }

        return $this->render('commandes/show.html.twig', [
            'commande' => $commande,
            'credit' => $credit,
            'livraisonActive' => $orderService->getLivraisonActive($commande),
        ]);
    }

    #[IsGranted('ROLE_DELEGUE')]
    #[Route('/{id}/valider', name: 'app_commande_valider', methods: ['POST'])]
    public function valider(Commande $commande, OrderManagerService $orderService): Response
    {
        try {
            $liv = $orderService->validerCommande($commande, $this->getUser());
            $this->addFlash('success', 'Commande ' . $commande->getNumero() . ' validée avec succès ! Bon de livraison généré : ' . $liv->getNumero());
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', 'Validation refusée : ' . $e->getMessage());
        }
        return $this->redirectToRoute('app_commande_show', ['id' => $commande->getId()]);
    }

    #[IsGranted('ROLE_DELEGUE')]
    #[Route('/{id}/nouveau-bl', name: 'app_commande_nouveau_bl', methods: ['POST'])]
    public function nouveauBl(Commande $commande, OrderManagerService $orderService): Response
    {
        try {
            $liv = $orderService->regenererLivraison($commande);
            $this->addFlash('success', 'Nouveau bon de livraison ' . $liv->getNumero() . ' généré pour la commande ' . $commande->getNumero() . '.');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('app_commande_show', ['id' => $commande->getId()]);
    }

    #[Route('/{id}/modifier', name: 'app_commande_modifier', methods: ['POST'])]
    public function modifier(Commande $commande, Request $request, EntityManagerInterface $em): Response
    {
        if (in_array($commande->getStatut(), ['SOUMISE', 'BROUILLON'])) {
            $commande->setObservation($request->request->get('observation', $commande->getObservation()));
            $em->flush();
            $this->addFlash('info', 'Commande ' . $commande->getNumero() . ' mise à jour.');
        } else {
            $this->addFlash('warning', 'Impossible de modifier une commande déjà validée ou expédiée.');
        }
        return $this->redirectToRoute('app_commande_index');
    }

    #[IsGranted('ROLE_DELEGUE')]
    #[Route('/{id}/annuler', name: 'app_commande_annuler', methods: ['POST'])]
    public function annuler(Commande $commande, OrderManagerService $orderService): Response
    {
        try {
            $orderService->annulerCommande($commande);
            $this->addFlash('warning', 'Commande ' . $commande->getNumero() . ' annulée (suppression logique) ainsi que ses BL non expédiés.');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectToRoute('app_commande_index');
    }
}
