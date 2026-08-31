<?php

namespace App\Controller;

use App\Entity\Production;
use App\Entity\ProductionLigne;
use App\Entity\Usine;
use App\Entity\Produit;
use App\Entity\Lot;
use App\Entity\Conditionnement;
use App\Entity\EmplacementStock;
use App\Service\StockManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/production')]
class ProductionController extends AbstractController
{
    #[Route('', name: 'app_production_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $productions = $em->getRepository(Production::class)->findBy([], ['dateProduction' => 'DESC']);
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
    public function valider(Production $production, EntityManagerInterface $em, StockManagerService $stockService): Response
    {
        if ($production->getStatut() === 'BROUILLON') {
            $production->setStatut('VALIDEE');

            // Find usine stock emplacement
            $emp = $em->getRepository(EmplacementStock::class)->findOneBy([
                'usine' => $production->getUsine(),
                'typeEmplacement' => 'USINE'
            ]);

            if ($emp) {
                foreach ($production->getLignes() as $ligne) {
                    $stockService->enregistrerMouvement(
                        'PRODUCTION',
                        $ligne->getProduit(),
                        (float) $ligne->getQuantite(),
                        null,
                        $emp,
                        $ligne->getLot(),
                        $ligne->getConditionnement(),
                        'PRODUCTION',
                        $production->getId(),
                        $this->getUser(),
                        'Validation production ' . $production->getNumero()
                    );
                }
            }

            $em->flush();
            $this->addFlash('success', 'Production ' . $production->getNumero() . ' validée et stock usine mis à jour !');
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
    public function annuler(Production $production, EntityManagerInterface $em): Response
    {
        if ($production->getStatut() !== 'VALIDEE') {
            $production->setStatut('ANNULEE');
            $em->flush();
            $this->addFlash('warning', 'Ordre de production ' . $production->getNumero() . ' annulé (suppression logique).');
        }
        return $this->redirectToRoute('app_production_index');
    }
}