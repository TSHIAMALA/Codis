<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Production;
use App\Entity\Stock;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Entity\Livraison;
use App\Entity\Paiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $totalGrossistes = $em->getRepository(Grossiste::class)->count(['statut' => 'ACTIF']);
        $totalDetailants = $em->getRepository(Detailant::class)->count(['statut' => 'ACTIF']);
        $totalCommandes = $em->getRepository(Commande::class)->count([]);
        $totalProductions = $em->getRepository(Production::class)->count(['statut' => 'VALIDEE']);
        $livraisonsEnCours = $em->getRepository(Livraison::class)->count(['statut' => ['PREPARATION', 'EXPEDIEE', 'EN_TRANSIT']]);

        // Calculate CA Total
        $commandes = $em->getRepository(Commande::class)->findBy(['statut' => ['VALIDEE', 'EXPEDIEE', 'LIVREE', 'RECEPTIONNEE']]);
        $chiffreAffaires = 0.0;
        foreach ($commandes as $c) {
            $chiffreAffaires += (float) $c->getMontantTotal();
        }

        $commandesRecentes = $em->getRepository(Commande::class)->findBy([], ['dateCommande' => 'DESC'], 6);

        // --- STOCKS STATISTICS & REGIONAL BREAKDOWN ---
        $stocksAll = $em->getRepository(Stock::class)->findAll();

        $totalStockDisponible = 0.0;
        $totalStockReserve = 0.0;
        $totalStockTransit = 0.0;
        $stocksParRegion = [];

        foreach ($stocksAll as $s) {
            $dispo = (float) $s->getQuantiteDisponible();
            $reser = (float) $s->getQuantiteReservee();
            $trans = (float) $s->getQuantiteTransit();

            $totalStockDisponible += $dispo;
            $totalStockReserve += $reser;
            $totalStockTransit += $trans;

            $regionNom = 'Kinshasa & Centre';
            $emp = $s->getEmplacement();
            if ($emp) {
                if ($emp->getDepot() && $emp->getDepot()->getRegion()) {
                    $regionNom = $emp->getDepot()->getRegion()->getNom();
                } elseif ($emp->getUsine() && $emp->getUsine()->getRegion()) {
                    $regionNom = $emp->getUsine()->getRegion()->getNom();
                } elseif ($emp->getDepot() && $emp->getDepot()->getPays()) {
                    $regionNom = $emp->getDepot()->getPays()->getNom();
                }
            }

            if (!isset($stocksParRegion[$regionNom])) {
                $stocksParRegion[$regionNom] = [
                    'nom' => $regionNom,
                    'disponible' => 0.0,
                    'reservee' => 0.0,
                    'transit' => 0.0,
                    'sites' => [],
                ];
            }
            $stocksParRegion[$regionNom]['disponible'] += $dispo;
            $stocksParRegion[$regionNom]['reservee'] += $reser;
            $stocksParRegion[$regionNom]['transit'] += $trans;

            $siteNom = $emp ? $emp->getNom() : '';
            if ($siteNom && !in_array($siteNom, $stocksParRegion[$regionNom]['sites'])) {
                $stocksParRegion[$regionNom]['sites'][] = $siteNom;
            }
        }

        $grandTotalStock = $totalStockDisponible + $totalStockReserve + $totalStockTransit;

        return $this->render('dashboard/index.html.twig', [
            'totalGrossistes' => $totalGrossistes,
            'totalDetailants' => $totalDetailants,
            'totalCommandes' => $totalCommandes,
            'totalProductions' => $totalProductions,
            'livraisonsEnCours' => $livraisonsEnCours,
            'chiffreAffaires' => $chiffreAffaires,
            'commandesRecentes' => $commandesRecentes,
            'totalStockDisponible' => $totalStockDisponible,
            'totalStockReserve' => $totalStockReserve,
            'totalStockTransit' => $totalStockTransit,
            'grandTotalStock' => $grandTotalStock,
            'stocksParRegion' => $stocksParRegion,
            'stocksDispo' => array_slice($stocksAll, 0, 6),
        ]);
    }
}