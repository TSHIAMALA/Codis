<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Entity\Production;
use App\Entity\Stock;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Entity\Livraison;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
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
        $livraisonsEnCours = $em->getRepository(Livraison::class)->count(['statut' => Livraison::STATUTS_EN_COURS]);

        // Chiffre d'affaires : somme calculée par la base
        $chiffreAffaires = (float) $em->createQueryBuilder()
            ->select('COALESCE(SUM(c.montantTotal), 0)')
            ->from(Commande::class, 'c')
            ->where('c.statut IN (:statuts)')
            ->setParameter('statuts', Commande::STATUTS_CHIFFRE_AFFAIRES)
            ->getQuery()->getSingleScalarResult();

        // Clients chargés dans la même requête (le nom du client est affiché sur chaque ligne)
        $commandesRecentes = $em->createQueryBuilder()
            ->select('c', 'g', 'd')
            ->from(Commande::class, 'c')
            ->leftJoin('c.grossiste', 'g')
            ->leftJoin('c.detailant', 'd')
            ->orderBy('c.dateCommande', 'DESC')
            ->setMaxResults(6)
            ->getQuery()->getResult();

        // --- STOCKS : totaux par emplacement calculés par la base, puis regroupés par région ---
        $parEmplacement = $em->createQueryBuilder()
            ->select(
                'e.nom AS site', 'e.typeEmplacement AS type',
                'rd.nom AS regionDepot', 'ru.nom AS regionUsine', 'pd.nom AS paysDepot',
                'SUM(s.quantiteDisponible) AS disponible', 'SUM(s.quantiteReservee) AS reservee', 'SUM(s.quantiteTransit) AS transit'
            )
            ->from(Stock::class, 's')
            ->join('s.emplacement', 'e')
            ->leftJoin('e.depot', 'dep')
            ->leftJoin('dep.region', 'rd')
            ->leftJoin('dep.pays', 'pd')
            ->leftJoin('e.usine', 'usi')
            ->leftJoin('usi.region', 'ru')
            ->groupBy('e.id', 'e.nom', 'e.typeEmplacement', 'rd.nom', 'ru.nom', 'pd.nom')
            ->getQuery()->getArrayResult();

        $totalStockDisponible = $totalStockReserve = $totalStockTransit = 0.0;
        $stocksParRegion = [];
        foreach ($parEmplacement as $ligne) {
            $regionNom = match (true) {
                $ligne['type'] === 'DR' => 'Stock confié aux DR (en tournée)',
                $ligne['regionDepot'] !== null => $ligne['regionDepot'],
                $ligne['regionUsine'] !== null => $ligne['regionUsine'],
                $ligne['paysDepot'] !== null => $ligne['paysDepot'],
                default => 'Kinshasa & Centre',
            };

            $stocksParRegion[$regionNom] ??= ['nom' => $regionNom, 'disponible' => 0.0, 'reservee' => 0.0, 'transit' => 0.0, 'sites' => []];
            $stocksParRegion[$regionNom]['disponible'] += (float) $ligne['disponible'];
            $stocksParRegion[$regionNom]['reservee'] += (float) $ligne['reservee'];
            $stocksParRegion[$regionNom]['transit'] += (float) $ligne['transit'];
            if (!in_array($ligne['site'], $stocksParRegion[$regionNom]['sites'], true)) {
                $stocksParRegion[$regionNom]['sites'][] = $ligne['site'];
            }

            $totalStockDisponible += (float) $ligne['disponible'];
            $totalStockReserve += (float) $ligne['reservee'];
            $totalStockTransit += (float) $ligne['transit'];
        }

        // Aperçu : dernières lignes de stock mises à jour, avec site, produit et lot en une requête
        $stocksDispo = $em->createQueryBuilder()
            ->select('s', 'e', 'p', 'l')
            ->from(Stock::class, 's')
            ->join('s.emplacement', 'e')
            ->join('s.produit', 'p')
            ->leftJoin('s.lot', 'l')
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults(6)
            ->getQuery()->getResult();

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
            'grandTotalStock' => $totalStockDisponible + $totalStockReserve + $totalStockTransit,
            'stocksParRegion' => $stocksParRegion,
            'stocksDispo' => $stocksDispo,
        ]);
    }
}
