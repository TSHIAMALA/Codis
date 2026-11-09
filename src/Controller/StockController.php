<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Entity\Produit;
use App\Pagination\Page;
use App\Entity\Depot;
use App\Entity\Usine;
use App\Entity\MouvementStock;
use App\Entity\TransfertStock;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
#[Route('/stocks')]
class StockController extends AbstractController
{
    #[Route('', name: 'app_stock_index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $activeTab = $request->query->get('tab', 'synthese');
        $depots = $em->getRepository(Depot::class)->findBy(['actif' => true]);

        // Synthèse par produit : totaux calculés par la base
        $synthese = $em->createQueryBuilder()
            ->select('p.id AS id', 'SUM(s.quantiteDisponible) AS disponible', 'SUM(s.quantiteReservee) AS reservee', 'SUM(s.quantiteTransit) AS transit', 'COUNT(s.id) AS nbLots')
            ->from(Stock::class, 's')
            ->join('s.produit', 'p')
            ->groupBy('p.id')
            ->getQuery()->getArrayResult();
        $sites = $em->createQueryBuilder()
            ->select('DISTINCT IDENTITY(s.produit) AS produit', 'e.nom AS site')
            ->from(Stock::class, 's')
            ->join('s.emplacement', 'e')
            ->getQuery()->getArrayResult();
        $produits = [];
        foreach ($em->createQueryBuilder()->select('p', 'c')->from(Produit::class, 'p')->leftJoin('p.categorie', 'c')->getQuery()->getResult() as $p) {
            $produits[$p->getId()] = $p;
        }

        $stocksParProduit = [];
        foreach ($synthese as $ligne) {
            $stocksParProduit[$ligne['id']] = [
                'produit' => $produits[$ligne['id']],
                'disponible' => (float) $ligne['disponible'],
                'reservee' => (float) $ligne['reservee'],
                'transit' => (float) $ligne['transit'],
                'nbLots' => (int) $ligne['nbLots'],
                'emplacements' => [],
            ];
        }
        foreach ($sites as $site) {
            $stocksParProduit[$site['produit']]['emplacements'][] = $site['site'];
        }

        // Détail par emplacement et lot : paginé (paramètre "page" de l'onglet actif)
        $stocks = Page::depuisRequete($em->createQueryBuilder()
            ->select('s', 'e', 'p', 'l', 'c')
            ->from(Stock::class, 's')
            ->join('s.emplacement', 'e')
            ->join('s.produit', 'p')
            ->leftJoin('s.lot', 'l')
            ->leftJoin('s.conditionnement', 'c')
            ->orderBy('e.nom')
            ->addOrderBy('p.nom')
            ->addOrderBy('l.dateExpiration'), $activeTab === 'detail' ? $request : new Request());

        $mouvements = Page::depuisRequete($em->createQueryBuilder()
            ->select('m', 'p', 'src', 'dst')
            ->from(MouvementStock::class, 'm')
            ->join('m.produit', 'p')
            ->leftJoin('m.emplacementSource', 'src')
            ->leftJoin('m.emplacementDestination', 'dst')
            ->orderBy('m.dateMouvement', 'DESC')
            ->addOrderBy('m.id', 'DESC'), $activeTab === 'mouvements' ? $request : new Request(), 30);

        return $this->render('stock/index.html.twig', [
            'stocks' => $stocks,
            'stocksParProduit' => $stocksParProduit,
            'depots' => $depots,
            'mouvements' => $mouvements,
            'activeTab' => $activeTab,
        ]);
    }
}