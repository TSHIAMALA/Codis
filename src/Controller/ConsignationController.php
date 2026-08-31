<?php

namespace App\Controller;

use App\Entity\DemandeConsignation;
use App\Entity\DemandeConsignationLigne;
use App\Entity\AgentCommercial;
use App\Entity\Depot;
use App\Entity\Produit;
use App\Entity\EmplacementStock;
use App\Service\StockManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/consignations')]
class ConsignationController extends AbstractController
{
    #[Route('', name: 'app_consignation_index')]
    public function index(EntityManagerInterface $em): Response
    {
        $consignations = $em->getRepository(DemandeConsignation::class)->findBy([], ['dateDemande' => 'DESC']);
        $agents = $em->getRepository(AgentCommercial::class)->findBy(['actif' => true]);
        $depots = $em->getRepository(Depot::class)->findBy(['actif' => true]);
        $produits = $em->getRepository(Produit::class)->findBy(['actif' => true]);

        return $this->render('consignation/index.html.twig', [
            'consignations' => $consignations,
            'agents' => $agents,
            'depots' => $depots,
            'produits' => $produits,
        ]);
    }

    #[Route('/nouveau', name: 'app_consignation_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em): Response
    {
        $drId = $request->request->get('dr_id');
        $depotId = $request->request->get('depot_id');
        $produitId = $request->request->get('produit_id');
        $quantite = $request->request->get('quantite', '0');
        $observation = $request->request->get('observation');

        $dr = $drId ? $em->getRepository(AgentCommercial::class)->find($drId) : null;
        $depot = $depotId ? $em->getRepository(Depot::class)->find($depotId) : null;
        $produit = $produitId ? $em->getRepository(Produit::class)->find($produitId) : null;

        if (!$dr || !$depot || !$produit || (float)$quantite <= 0) {
            $this->addFlash('danger', 'Veuillez remplir les informations obligatoires (*) pour le prélèvement.');
            return $this->redirectToRoute('app_consignation_index');
        }

        $c = new DemandeConsignation();
        $c->setNumero('CONS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)));
        $c->setDr($dr);
        $c->setDepot($depot);
        $c->setDateDemande(new \DateTime());
        $c->setStatut('SOUMISE');
        $c->setObservation($observation);

        $ligne = new DemandeConsignationLigne();
        $ligne->setDemande($c);
        $ligne->setProduit($produit);
        $ligne->setQuantiteDemandee((string) $quantite);
        $ligne->setQuantiteAccordee((string) $quantite);
        $em->persist($ligne);

        $em->persist($c);
        $em->flush();

        $this->addFlash('success', 'Demande de prélèvement ' . $c->getNumero() . ' créée par le DR ' . ($dr->getUtilisateur() ? $dr->getUtilisateur()->getNomComplet() : '') . ' (En attente validation Superviseur).');
        return $this->redirectToRoute('app_consignation_index');
    }

    #[Route('/{id}/valider-superviseur', name: 'app_consignation_valider_superviseur', methods: ['POST'])]
    public function validerSuperviseur(DemandeConsignation $c, EntityManagerInterface $em): Response
    {
        if ($c->getStatut() === 'SOUMISE') {
            $c->setStatut('VALIDEE_SUPERVISEUR');
            $c->setDateValidationSuperviseur(new \DateTime());
            $em->flush();

            $this->addFlash('info', 'Demande ' . $c->getNumero() . ' approuvée par le Superviseur des Ventes (En attente validation finale ASM).');
        }
        return $this->redirectToRoute('app_consignation_index');
    }

    #[Route('/{id}/valider-asm', name: 'app_consignation_valider_asm', methods: ['POST'])]
    public function validerAsm(DemandeConsignation $c, EntityManagerInterface $em, StockManagerService $stockService): Response
    {
        if (in_array($c->getStatut(), ['SOUMISE', 'VALIDEE_SUPERVISEUR'])) {
            $c->setStatut('VALIDEE_ASM');
            $c->setDateValidationAsm(new \DateTime());

            // Sortie de stock du dépôt vers le DR (Consignation)
            $emp = $em->getRepository(EmplacementStock::class)->findOneBy([
                'depot' => $c->getDepot(),
                'typeEmplacement' => 'DEPOT'
            ]);

            if ($emp) {
                foreach ($c->getLignes() as $ligne) {
                    $stockService->enregistrerMouvement(
                        'TRANSFERT',
                        $ligne->getProduit(),
                        (float) $ligne->getQuantiteAccordee(),
                        $emp,
                        null,
                        null,
                        null,
                        'CONSIGNATION_DR',
                        $c->getId(),
                        $this->getUser(),
                        'Sortie prélèvement consignation DR ' . $c->getNumero()
                    );
                }
            }

            $em->flush();
            $this->addFlash('success', 'Prélèvement ' . $c->getNumero() . ' validé par l\'ASM ! Stock sorti du dépôt et confié au DR pour sa tournée.');
        }
        return $this->redirectToRoute('app_consignation_index');
    }

    #[Route('/{id}/cloturer', name: 'app_consignation_cloturer', methods: ['POST'])]
    public function cloturer(DemandeConsignation $c, Request $request, EntityManagerInterface $em, StockManagerService $stockService): Response
    {
        if ($c->getStatut() === 'VALIDEE_ASM') {
            $cash = $request->request->get('cashJustifie', '0.00');
            $c->setCashJustifie((string) $cash);
            $c->setStatut('CLOTUREE');

            $emp = $em->getRepository(EmplacementStock::class)->findOneBy([
                'depot' => $c->getDepot(),
                'typeEmplacement' => 'DEPOT'
            ]);

            foreach ($c->getLignes() as $ligne) {
                $qteVendue = $request->request->get('quantiteVendue_' . $ligne->getId(), $ligne->getQuantiteAccordee());
                $qteRetournee = $request->request->get('quantiteRetournee_' . $ligne->getId(), '0');

                $ligne->setQuantiteVendue((string) $qteVendue);
                $ligne->setQuantiteRetournee((string) $qteRetournee);

                // Re-créditer les invendus dans le stock du dépôt
                if ((float)$qteRetournee > 0 && $emp) {
                    $stockService->enregistrerMouvement(
                        'RETOUR',
                        $ligne->getProduit(),
                        (float) $qteRetournee,
                        null,
                        $emp,
                        null,
                        null,
                        'RETOUR_INVENDU_DR',
                        $c->getId(),
                        $this->getUser(),
                        'Retour invendus de fin de journée DR ' . $c->getNumero()
                    );
                }
            }

            $em->flush();
            $this->addFlash('success', 'Clôture de journée enregistrée pour le DR ! Cash justifié : ' . number_format((float)$cash, 2, ',', ' ') . ' $, invendus réintégrés en stock.');
        }
        return $this->redirectToRoute('app_consignation_index');
    }
}
