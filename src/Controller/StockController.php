<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Entity\Depot;
use App\Entity\Usine;
use App\Entity\MouvementStock;
use App\Entity\TransfertStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/stocks')]
class StockController extends AbstractController
{
    #[Route('', name: 'app_stock_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $activeTab = $request->query->get('tab', 'synthese');
        $stocks = $em->getRepository(Stock::class)->findAll();
        $depots = $em->getRepository(Depot::class)->findBy(['actif' => true]);
        $mouvements = $em->getRepository(MouvementStock::class)->findBy([], ['dateMouvement' => 'DESC'], 30);
        $transferts = $em->getRepository(TransfertStock::class)->findBy([], ['dateDemande' => 'DESC'], 20);

        // Aggregated stock summary per product across all locations & lots
        $stocksParProduit = [];
        foreach ($stocks as $s) {
            $p = $s->getProduit();
            if (!$p) continue;
            $pId = $p->getId();
            if (!isset($stocksParProduit[$pId])) {
                $stocksParProduit[$pId] = [
                    'produit' => $p,
                    'disponible' => 0.0,
                    'reservee' => 0.0,
                    'transit' => 0.0,
                    'nbLots' => 0,
                    'emplacements' => [],
                ];
            }
            $stocksParProduit[$pId]['disponible'] += (float)$s->getQuantiteDisponible();
            $stocksParProduit[$pId]['reservee'] += (float)$s->getQuantiteReservee();
            $stocksParProduit[$pId]['transit'] += (float)$s->getQuantiteTransit();
            $stocksParProduit[$pId]['nbLots']++;
            $empNom = $s->getEmplacement() ? $s->getEmplacement()->getNom() : '';
            if ($empNom && !in_array($empNom, $stocksParProduit[$pId]['emplacements'])) {
                $stocksParProduit[$pId]['emplacements'][] = $empNom;
            }
        }

        return $this->render('stock/index.html.twig', [
            'stocks' => $stocks,
            'stocksParProduit' => $stocksParProduit,
            'depots' => $depots,
            'mouvements' => $mouvements,
            'transferts' => $transferts,
            'activeTab' => $activeTab,
        ]);
    }
}