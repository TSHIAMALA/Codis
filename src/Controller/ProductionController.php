<?php

namespace App\Controller;

use App\Entity\Production;
use App\Entity\ProductionLigne;
use App\Entity\Usine;
use App\Entity\Produit;
use App\Entity\Lot;
use App\Entity\Conditionnement;
use App\Entity\EmplacementStock;
use App\Exception\OperationImpossibleException;
use App\Pagination\Page;
use App\Service\StockManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

#[IsGranted('ROLE_RESPONSABLE_USINE', message: 'La production est réservée aux responsables usine.')]
#[Route('/production')]
class ProductionController extends AbstractController
{
    #[Route('', name: 'app_production_index')]
    public function index(HttpRequest $request, EntityManagerInterface $em): Response
    {
        $productions = Page::depuisRequete($em->createQueryBuilder()
            ->select('pr', 'u', 'l', 'p', 'lot')
            ->from(Production::class, 'pr')
            ->leftJoin('pr.usine', 'u')
            ->leftJoin('pr.lignes', 'l')
            ->leftJoin('l.produit', 'p')
            ->leftJoin('l.lot', 'lot')
            ->orderBy('pr.dateProduction', 'DESC')
            ->addOrderBy('pr.id', 'DESC'), $request, jointureCollection: true);
        $usines = $em->getRepository(Usine::class)->findBy(['actif' => true]);
        $produits = $em->getRepository(Produit::class)->findBy(['actif' => true]);
        $lots = $em->getRepository(Lot::class)->findBy([], ['dateFabrication' => 'DESC'], 20);

        return $this->render('production/index.html.twig', [
            'productions' => $productions,
            'usines' => $usines,
            'produits' => $produits,
            'lots' => $lots,
        ]);
    }

    #[Route('/nouveau', name: 'app_production_nouveau', methods: ['POST'])]
    public function nouveau(HttpRequest $request, EntityManagerInterface $em): Response
    {
        $usineId = $request->request->get('usine_id');
        $produitId = $request->request->get('produit_id');
        $quantite = $request->request->get('quantite', '0');
        $dateProductionStr = $request->request->get('dateProduction');
        $observation = $request->request->get('observation');

        $usine = $usineId ? $em->getRepository(Usine::class)->find($usineId) : null;
        $produit = $produitId ? $em->getRepository(Produit::class)->find($produitId) : null;

        if (!$usine || !$produit || (float)$quantite <= 0) {
            $this->addFlash('danger', 'Veuillez sélectionner une usine, un produit et saisir une quantité valide.');
            return $this->redirectToRoute('app_production_index');
        }

        $dateProd = !empty($dateProductionStr) ? new \DateTime($dateProductionStr) : new \DateTime();

        // Create Production
        $p = new Production();
        $p->setUsine($usine);
        $p->setNumero('OF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
        $p->setDateProduction($dateProd);
        $p->setObservation($observation);
        $p->setStatut('BROUILLON');
        $p->setUtilisateur($this->getUser());

        // Create Lot for this production batch
        $lot = new Lot();
        $lot->setUsine($usine);
        $lot->setProduit($produit);
        $lot->setNumeroLot('LOT-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
        $lot->setDateFabrication($dateProd);
        $lot->setDateExpiration((clone $dateProd)->modify('+2 years'));
        $lot->setStatut('ACTIF');
        $em->persist($lot);

        // Find conditionnement for this product
        $cond = $em->getRepository(Conditionnement::class)->findOneBy(['produit' => $produit]);

        // Create Production Ligne
        $ligne = new ProductionLigne();
        $ligne->setProduction($p);
        $ligne->setProduit($produit);
        $ligne->setLot($lot);
        $ligne->setConditionnement($cond);
        $ligne->setQuantite((string) $quantite);
        $em->persist($ligne);

        $em->persist($p);
        $em->flush();

        $this->addFlash('success', 'Ordre de fabrication ' . $p->getNumero() . ' créé avec succès (Statut: Brouillon). Vous pouvez maintenant le valider pour alimenter le stock.');
        return $this->redirectToRoute('app_production_index');
    }

    #[Route('/{id}/valider', name: 'app_production_valider', methods: ['POST'])]
    public function valider(
        Production $production,
        EntityManagerInterface $em,
        StockManagerService $stockService,
        #[Target('production')] WorkflowInterface $productionWorkflow
    ): Response {
        if (!$productionWorkflow->can($production, 'valider')) {
            $this->addFlash('warning', 'L\'ordre de production ' . $production->getNumero() . ' a déjà été traité (statut : ' . $production->getStatut() . ').');
            return $this->redirectToRoute('app_production_index');
        }

        $emp = $em->getRepository(EmplacementStock::class)->findOneBy([
            'usine' => $production->getUsine(),
            'typeEmplacement' => 'USINE',
            'actif' => true,
        ]);
        if (!$emp) {
            $this->addFlash('danger', 'Validation impossible : aucun emplacement de stock actif pour l\'usine ' . ($production->getUsine()?->getNom() ?? '') . '.');
            return $this->redirectToRoute('app_production_index');
        }

        try {
            $em->wrapInTransaction(function () use ($production, $emp, $stockService, $productionWorkflow) {
                foreach ($production->getLignes() as $ligne) {
                    $stockService->entrer(
                        'PRODUCTION',
                        $ligne->getProduit(),
                        (float) $ligne->getQuantite(),
                        $emp,
                        $ligne->getLot(),
                        $ligne->getConditionnement(),
                        'PRODUCTION',
                        $production->getId(),
                        $this->getUser(),
                        'Validation production ' . $production->getNumero()
                    );
                }
                $productionWorkflow->apply($production, 'valider');
            });
            $this->addFlash('success', 'Production ' . $production->getNumero() . ' validée et stock usine mis à jour !');
        } catch (OperationImpossibleException $e) {
            $this->addFlash('danger', 'Validation refusée : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_production_index');
    }

    #[Route('/{id}/modifier', name: 'app_production_modifier', methods: ['POST'])]
    public function modifier(Production $production, HttpRequest $request, EntityManagerInterface $em): Response
    {
        if ($production->getStatut() === 'BROUILLON') {
            $production->setObservation($request->request->get('observation', $production->getObservation()));
            $dateStr = $request->request->get('dateProduction');
            if (!empty($dateStr)) {
                $production->setDateProduction(new \DateTime($dateStr));
            }
            $em->flush();
            $this->addFlash('info', 'Ordre de production ' . $production->getNumero() . ' mis à jour.');
        } else {
            $this->addFlash('warning', 'Impossible de modifier un ordre de production déjà validé.');
        }
        return $this->redirectToRoute('app_production_index');
    }

    #[Route('/{id}/annuler', name: 'app_production_annuler', methods: ['POST'])]
    public function annuler(Production $production, EntityManagerInterface $em, #[Target('production')] WorkflowInterface $productionWorkflow): Response
    {
        if ($productionWorkflow->can($production, 'annuler')) {
            $productionWorkflow->apply($production, 'annuler');
            $em->flush();
            $this->addFlash('warning', 'Ordre de production ' . $production->getNumero() . ' annulé (suppression logique).');
        } else {
            $this->addFlash('danger', 'Un ordre de production validé ne peut plus être annulé : son stock est déjà entré en usine.');
        }
        return $this->redirectToRoute('app_production_index');
    }
}