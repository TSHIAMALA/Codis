<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\CommandeLigne;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Entity\Depot;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Service\OrderManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/commandes')]
class CommandeController extends AbstractController
{
    #[Route('', name: 'app_commande_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $commandes = $em->getRepository(Commande::class)->findBy([], ['dateCommande' => 'DESC']);
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
    public function nouveau(Request $request, EntityManagerInterface $em): Response
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
        $prixUnitaires = $request->request->all('prix_unitaires');

        if (empty($produitIds) || count($produitIds) === 0) {
            $this->addFlash('danger', 'Veuillez ajouter au moins une ligne de produit à la commande.');
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

        $montantTotal = 0.0;

        for ($i = 0; $i < count($produitIds); $i++) {
            $produit = $em->getRepository(Produit::class)->find($produitIds[$i]);
            $conditionnement = isset($conditionnementIds[$i]) ? $em->getRepository(Conditionnement::class)->find($conditionnementIds[$i]) : null;
            $qte = isset($quantites[$i]) ? (float) $quantites[$i] : 0;
            $prixU = isset($prixUnitaires[$i]) ? (float) $prixUnitaires[$i] : 0;

            if (!$produit || $qte <= 0) {
                continue;
            }

            $montantLigne = $qte * $prixU;

            $ligne = new CommandeLigne();
            $ligne->setCommande($commande);
            $ligne->setProduit($produit);
            if ($conditionnement) {
                $ligne->setConditionnement($conditionnement);
            }
            $ligne->setQuantite((string) $qte);
            $ligne->setPrixUnitaire((string) $prixU);
            $ligne->setMontant((string) $montantLigne);

            $em->persist($ligne);
            $montantTotal += $montantLigne;
        }

        $commande->setMontantTotal((string) $montantTotal);

        $em->persist($commande);
        $em->flush();

        $this->addFlash('success', 'Commande ' . $commande->getNumero() . ' créée avec succès pour ' . $commande->getClientNom() . ' — Montant : ' . number_format($montantTotal, 2, ',', ' ') . ' $.');
        return $this->redirectToRoute('app_commande_index');
    }

    #[Route('/{id}', name: 'app_commande_show')]
    public function show(Commande $commande): Response
    {
        return $this->render('commandes/show.html.twig', [
            'commande' => $commande,
        ]);
    }

    #[Route('/{id}/valider', name: 'app_commande_valider', methods: ['POST'])]
    public function valider(Commande $commande, OrderManagerService $orderService): Response
    {
        if (in_array($commande->getStatut(), ['SOUMISE', 'BROUILLON'])) {
            $liv = $orderService->validerCommande($commande, $this->getUser());
            $this->addFlash('success', 'Commande ' . $commande->getNumero() . ' validée avec succès ! Bon de livraison généré : ' . $liv->getNumero());
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

    #[Route('/{id}/annuler', name: 'app_commande_annuler', methods: ['POST'])]
    public function annuler(Commande $commande, EntityManagerInterface $em): Response
    {
        if (!in_array($commande->getStatut(), ['LIVREE', 'RECEPTIONNEE', 'ANNULEE'])) {
            // Soft delete: update statut to ANNULEE
            $commande->setStatut('ANNULEE');
            $em->flush();
            $this->addFlash('warning', 'Commande ' . $commande->getNumero() . ' annulée (suppression logique).');
        }
        return $this->redirectToRoute('app_commande_index');
    }
}