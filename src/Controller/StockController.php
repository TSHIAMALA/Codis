<?php

namespace App\Controller;

use App\Entity\Stock;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Entity\EmplacementStock;
use App\Exception\OperationImpossibleException;
use App\Service\StockManagerService;
use App\Service\TransfertService;
use Symfony\Component\ExpressionLanguage\Expression;
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
    public function index(Request $request, EntityManagerInterface $em, StockManagerService $stockService): Response
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

        // Transferts entre sites : liste paginée + données du formulaire
        $transferts = Page::depuisRequete($em->createQueryBuilder()
            ->select('t', 'src', 'dst', 'l', 'lp', 'lc', 'll')
            ->from(TransfertStock::class, 't')
            ->leftJoin('t.emplacementSource', 'src')
            ->leftJoin('t.emplacementDestination', 'dst')
            ->leftJoin('t.lignes', 'l')
            ->leftJoin('l.produit', 'lp')
            ->leftJoin('l.conditionnement', 'lc')
            ->leftJoin('l.lot', 'll')
            ->orderBy('t.id', 'DESC'), $activeTab === 'transferts' ? $request : new Request(), jointureCollection: true);
        $sitesTransfert = $em->getRepository(EmplacementStock::class)->findBy(['typeEmplacement' => ['USINE', 'DEPOT'], 'actif' => true], ['typeEmplacement' => 'DESC', 'nom' => 'ASC']);
        $disponibles = [];
        foreach ($stockService->getDisponibles($sitesTransfert) as $cle => $qte) {
            [$e, , $c] = explode('-', $cle);
            if ($c !== '') {
                $disponibles[$e][$c] = $qte;
            }
        }

        return $this->render('stock/index.html.twig', [
            'transferts' => $transferts,
            'sitesTransfert' => $sitesTransfert,
            'disponiblesParSite' => $disponibles,
            'produits' => $em->getRepository(Produit::class)->findBy(['actif' => true], ['nom' => 'ASC']),
            'conditionnements' => $em->getRepository(Conditionnement::class)->findBy([], ['niveau' => 'DESC']),
            'stocks' => $stocks,
            'stocksParProduit' => $stocksParProduit,
            'depots' => $depots,
            'mouvements' => $mouvements,
            'activeTab' => $activeTab,
        ]);
    }
    #[IsGranted(new Expression("is_granted('ROLE_RESPONSABLE_USINE') or is_granted('ROLE_GESTIONNAIRE_DEPOT')"), message: 'Les transferts sont réservés aux responsables usine et aux gestionnaires de dépôt.')]
    #[Route('/transferts/nouveau', name: 'app_transfert_nouveau', methods: ['POST'])]
    public function nouveauTransfert(Request $request, EntityManagerInterface $em, TransfertService $transfertService): Response
    {
        $source = $em->getRepository(EmplacementStock::class)->find((int) $request->request->get('source_id'));
        $destination = $em->getRepository(EmplacementStock::class)->find((int) $request->request->get('destination_id'));
        $produit = $em->getRepository(Produit::class)->find((int) $request->request->get('produit_id'));
        $cond = $em->getRepository(Conditionnement::class)->find((int) $request->request->get('conditionnement_id'));
        $quantite = (float) $request->request->get('quantite');

        if (!$source || !$destination || !$produit || !$cond || $quantite <= 0) {
            $this->addFlash('danger', 'Renseignez le site de départ, le dépôt d\'arrivée, le produit, le conditionnement et une quantité positive.');
            return $this->redirectToRoute('app_stock_index', ['tab' => 'transferts']);
        }

        try {
            $t = $transfertService->expedier($source, $destination, $produit, $cond, $quantite, $this->getUser(), $request->request->get('observation'));
            $this->addFlash('success', 'Transfert ' . $t->getNumero() . ' expédié : ' . $quantite . ' ' . $cond->getLibelle() . '(s) de ' . $produit->getNom() . ' en route de « ' . $source->getNom() . ' » vers « ' . $destination->getNom() . ' ».');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', 'Transfert refusé : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_index', ['tab' => 'transferts']);
    }

    #[IsGranted('ROLE_GESTIONNAIRE_DEPOT', message: 'La réception est faite par le gestionnaire du dépôt d\'arrivée.')]
    #[Route('/transferts/{id}/receptionner', name: 'app_transfert_receptionner', methods: ['POST'])]
    public function receptionnerTransfert(TransfertStock $t, TransfertService $transfertService): Response
    {
        try {
            $transfertService->receptionner($t, $this->getUser());
            $this->addFlash('success', 'Transfert ' . $t->getNumero() . ' réceptionné : le stock est entré à « ' . $t->getEmplacementDestination()->getNom() . ' ».');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_index', ['tab' => 'transferts']);
    }

    #[IsGranted(new Expression("is_granted('ROLE_RESPONSABLE_USINE') or is_granted('ROLE_GESTIONNAIRE_DEPOT')"))]
    #[Route('/transferts/{id}/annuler', name: 'app_transfert_annuler', methods: ['POST'])]
    public function annulerTransfert(TransfertStock $t, TransfertService $transfertService): Response
    {
        try {
            $transfertService->annuler($t, $this->getUser());
            $this->addFlash('warning', 'Transfert ' . $t->getNumero() . ' annulé : la marchandise en transit est revenue au site de départ.');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_stock_index', ['tab' => 'transferts']);
    }
}
