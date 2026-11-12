<?php

namespace App\Controller;

use App\Entity\EmplacementStock;
use App\Entity\Livraison;
use App\Exception\OperationImpossibleException;
use App\Pagination\Page;
use App\Service\LivraisonService;
use App\Service\StockManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_GESTIONNAIRE_DEPOT', message: 'Les livraisons sont réservées aux gestionnaires de dépôt.')]
#[Route('/livraisons')]
class LivraisonController extends AbstractController
{
    #[Route('', name: 'app_livraison_index')]
    public function index(Request $request, EntityManagerInterface $em, LivraisonService $livraisonService, StockManagerService $stockService): Response
    {
        // Commande, client, paiements et lignes chargés avec chaque BL (affichés et utilisés par les contrôles)
        $qb = $em->createQueryBuilder()
            ->select('l', 'c', 'g', 'd', 'dep', 'p', 'li', 'prod', 'cond')
            ->from(Livraison::class, 'l')
            ->leftJoin('l.commande', 'c')
            ->leftJoin('c.grossiste', 'g')
            ->leftJoin('c.detailant', 'd')
            ->leftJoin('l.depot', 'dep')
            ->leftJoin('c.paiements', 'p')
            ->leftJoin('l.lignes', 'li')
            ->leftJoin('li.produit', 'prod')
            ->leftJoin('li.conditionnement', 'cond')
            ->orderBy('l.id', 'DESC');
        if ($statut = $request->query->get('statut')) {
            $statut === 'EN_COURS'
                ? $qb->andWhere('l.statut IN (:statuts)')->setParameter('statuts', Livraison::STATUTS_EN_COURS)
                : $qb->andWhere('l.statut = :statut')->setParameter('statut', $statut);
        }
        $livraisons = Page::depuisRequete($qb, $request, jointureCollection: true);

        // Pour les BL à expédier : paiement requis ? stock suffisant au dépôt ?
        // Emplacements et stocks disponibles lus une seule fois pour toute la page
        $aExpedier = array_filter(iterator_to_array($livraisons), fn (Livraison $l) => in_array($l->getStatut(), ['PLANIFIEE', 'PREPARATION'], true));
        $depots = array_unique(array_filter(array_map(fn (Livraison $l) => $l->getDepot(), $aExpedier)), SORT_REGULAR);
        $emplacements = $depots ? $em->getRepository(EmplacementStock::class)->findBy(['depot' => $depots, 'typeEmplacement' => 'DEPOT', 'actif' => true]) : [];
        $empParDepot = [];
        foreach ($emplacements as $e) {
            $empParDepot[$e->getDepot()->getId()] = $e;
        }
        $disponibles = $stockService->getDisponibles($emplacements);

        $controles = [];
        foreach ($aExpedier as $l) {
            $manquants = [];
            $emp = $l->getDepot() ? ($empParDepot[$l->getDepot()->getId()] ?? null) : null;
            foreach ($l->getLignes() as $ligne) {
                $besoin = (float) ($ligne->getQuantiteLivree() ?? $ligne->getQuantitePrevue());
                $dispo = $emp ? ($disponibles[StockManagerService::cleDisponible($emp, $ligne->getProduit(), $ligne->getConditionnement())] ?? 0.0) : 0.0;
                if ($dispo + 0.0005 < $besoin) {
                    $manquants[] = sprintf('%s (%s) : %s / %s', $ligne->getProduit()->getNom(), $ligne->getConditionnement()?->getLibelle() ?? '-', $dispo, $besoin);
                }
            }
            $controles[$l->getId()] = [
                'paiementRequis' => $livraisonService->paiementRequis($l),
                'manquants' => $manquants,
            ];
        }

        return $this->render('livraisons/index.html.twig', [
            'livraisons' => $livraisons,
            'controles' => $controles,
        ]);
    }

    #[Route('/{id}/expedier', name: 'app_livraison_expedier', methods: ['POST'])]
    public function expedier(Livraison $livraison, LivraisonService $livraisonService): Response
    {
        try {
            $livraisonService->expedier($livraison, $this->getUser());
            $this->addFlash('info', 'Livraison ' . $livraison->getNumero() . ' expédiée (En transit) : stock sorti du dépôt.');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', 'Expédition refusée : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_livraison_index');
    }

    #[Route('/{id}/confirmer', name: 'app_livraison_confirmer', methods: ['POST'])]
    public function confirmer(Livraison $livraison, LivraisonService $livraisonService): Response
    {
        try {
            $livraisonService->confirmerReception($livraison);
            $this->addFlash('success', 'Réception du Bon de Livraison ' . $livraison->getNumero() . ' confirmée avec succès !');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('warning', $e->getMessage());
        }

        return $this->redirectToRoute('app_livraison_index');
    }

    #[Route('/{id}/modifier', name: 'app_livraison_modifier', methods: ['POST'])]
    public function modifier(Livraison $livraison, Request $request, EntityManagerInterface $em): Response
    {
        $livraison->setObservation($request->request->get('observation', $livraison->getObservation()));
        $datePrevueStr = $request->request->get('datePrevue');
        if (!empty($datePrevueStr)) {
            $livraison->setDatePrevue(new \DateTime($datePrevueStr));
        }

        $em->flush();
        $this->addFlash('info', 'Bon de Livraison ' . $livraison->getNumero() . ' mis à jour.');
        return $this->redirectToRoute('app_livraison_index');
    }

    #[Route('/{id}/annuler', name: 'app_livraison_annuler', methods: ['POST'])]
    public function annuler(Livraison $livraison, LivraisonService $livraisonService): Response
    {
        try {
            $reintegre = $livraisonService->annuler($livraison, $this->getUser());
            $this->addFlash('warning', 'Livraison ' . $livraison->getNumero() . ' annulée'
                . ($reintegre ? ' : marchandise réintégrée au stock du dépôt.' : ' (suppression logique).')
                . ' La commande peut recevoir un nouveau BL depuis sa fiche.');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_livraison_index');
    }
}
