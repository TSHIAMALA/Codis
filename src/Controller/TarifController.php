<?php

namespace App\Controller;

use App\Entity\Tarif;
use App\Entity\Produit;
use App\Entity\Conditionnement;
use App\Entity\Region;
use App\Entity\ZoneCommerciale;
use App\Entity\Pays;
use App\Entity\Grossiste;
use App\Entity\Detailant;
use App\Service\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tarifs')]
class TarifController extends AbstractController
{
    #[Route('', name: 'app_tarif_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $regionFilter = $request->query->get('region_id');
        $produitFilter = $request->query->get('produit_id');
        $typeClientFilter = $request->query->get('type_client');

        $criteria = [];
        if (!empty($regionFilter)) {
            $criteria['region'] = $em->getRepository(Region::class)->find($regionFilter);
        }
        if (!empty($produitFilter)) {
            $criteria['produit'] = $em->getRepository(Produit::class)->find($produitFilter);
        }
        if (!empty($typeClientFilter)) {
            $criteria['typeClient'] = $typeClientFilter;
        }

        $tarifs = $em->getRepository(Tarif::class)->findBy($criteria, ['dateDebut' => 'DESC', 'id' => 'DESC']);
        $produits = $em->getRepository(Produit::class)->findBy(['actif' => true]);
        $regions = $em->getRepository(Region::class)->findAll();
        $zones = $em->getRepository(ZoneCommerciale::class)->findBy(['actif' => true]);
        $paysList = $em->getRepository(Pays::class)->findAll();
        $conditionnements = $em->getRepository(Conditionnement::class)->findAll();

        // Calcul des métriques
        $totalTarifs = count($tarifs);
        $regionsCount = count($regions);
        $produitsTarifes = [];
        $tarifsActifs = 0;
        foreach ($tarifs as $t) {
            if ($t->isActif()) {
                $tarifsActifs++;
                if ($t->getProduit()) {
                    $produitsTarifes[$t->getProduit()->getId()] = true;
                }
            }
        }

        // Grille matricielle pour affichage comparatif (Produit x Région)
        $matrice = [];
        foreach ($produits as $p) {
            $pId = $p->getId();
            $matrice[$pId] = [
                'produit' => $p,
                'lignes' => []
            ];
            foreach ($p->getConditionnements() as $cond) {
                $condId = $cond->getId();
                $matrice[$pId]['lignes'][$condId] = [
                    'conditionnement' => $cond,
                    'prix_par_region' => []
                ];
                foreach ($regions as $r) {
                    $rId = $r->getId();
                    // Trouver le tarif actif pour ce produit, conditionnement et région
                    $tGrossiste = $em->getRepository(Tarif::class)->findOneBy([
                        'produit' => $p,
                        'conditionnement' => $cond,
                        'region' => $r,
                        'typeClient' => 'GROSSISTE',
                        'actif' => true
                    ]);
                    $tDetaillant = $em->getRepository(Tarif::class)->findOneBy([
                        'produit' => $p,
                        'conditionnement' => $cond,
                        'region' => $r,
                        'typeClient' => 'DETAILLANT',
                        'actif' => true
                    ]);

                    $matrice[$pId]['lignes'][$condId]['prix_par_region'][$rId] = [
                        'grossiste' => $tGrossiste ? (float) $tGrossiste->getPrix() : null,
                        'detaillant' => $tDetaillant ? (float) $tDetaillant->getPrix() : null,
                    ];
                }
            }
        }

        return $this->render('tarif/index.html.twig', [
            'tarifs' => $tarifs,
            'produits' => $produits,
            'regions' => $regions,
            'zones' => $zones,
            'paysList' => $paysList,
            'conditionnements' => $conditionnements,
            'matrice' => $matrice,
            'totalTarifs' => $totalTarifs,
            'tarifsActifs' => $tarifsActifs,
            'regionsCount' => $regionsCount,
            'produitsTarifesCount' => count($produitsTarifes),
            'currentRegion' => $regionFilter,
            'currentProduit' => $produitFilter,
            'currentTypeClient' => $typeClientFilter,
        ]);
    }

    #[Route('/nouveau', name: 'app_tarif_nouveau', methods: ['POST'])]
    public function nouveau(Request $request, EntityManagerInterface $em, AuditService $audit): Response
    {
        $produitId = $request->request->get('produit_id');
        $conditionnementId = $request->request->get('conditionnement_id');
        $regionId = $request->request->get('region_id');
        $zoneId = $request->request->get('zone_id');
        $paysId = $request->request->get('pays_id');
        $typeClient = $request->request->get('type_client', 'GROSSISTE');
        $prix = (float) $request->request->get('prix', 0);
        $dateDebut = $request->request->get('date_debut');
        $dateFin = $request->request->get('date_fin');

        $produit = $em->getRepository(Produit::class)->find($produitId);
        $conditionnement = $em->getRepository(Conditionnement::class)->find($conditionnementId);
        $region = !empty($regionId) ? $em->getRepository(Region::class)->find($regionId) : null;
        $zone = !empty($zoneId) ? $em->getRepository(ZoneCommerciale::class)->find($zoneId) : null;
        $pays = !empty($paysId) ? $em->getRepository(Pays::class)->find($paysId) : ($region ? $region->getPays() : $em->getRepository(Pays::class)->findOneBy([]));

        if (!$produit || !$conditionnement || $prix <= 0) {
            $this->addFlash('danger', 'Veuillez renseigner correctement le produit, le conditionnement et un prix supérieur à 0.');
            return $this->redirectToRoute('app_tarif_index');
        }

        // Désactiver l'ancien tarif identique actif si existant
        $criteria = [
            'produit' => $produit,
            'conditionnement' => $conditionnement,
            'region' => $region,
            'zone' => $zone,
            'typeClient' => $typeClient,
            'actif' => true
        ];
        $ancienTarifs = $em->getRepository(Tarif::class)->findBy($criteria);
        foreach ($ancienTarifs as $at) {
            $at->setActif(false);
            if (!$at->getDateFin()) {
                $at->setDateFin(new \DateTime());
            }
        }

        $tarif = new Tarif();
        $tarif->setProduit($produit);
        $tarif->setConditionnement($conditionnement);
        $tarif->setPays($pays ?: $em->getRepository(Pays::class)->findOneBy([]));
        $tarif->setRegion($region);
        $tarif->setZone($zone);
        $tarif->setTypeClient($typeClient);
        $tarif->setPrix((string) $prix);
        $tarif->setActif(true);

        if (!empty($dateDebut)) {
            $tarif->setDateDebut(new \DateTime($dateDebut));
        } else {
            $tarif->setDateDebut(new \DateTime());
        }

        if (!empty($dateFin)) {
            $tarif->setDateFin(new \DateTime($dateFin));
        }

        $em->persist($tarif);
        $em->flush();

        $audit->log('tarif', 'CREATE', $tarif->getId(), null, [
            'produit' => $produit->getNom(),
            'conditionnement' => $conditionnement->getLibelle(),
            'region' => $region ? $region->getNom() : 'National',
            'zone' => $zone ? $zone->getNom() : 'Toutes',
            'typeClient' => $typeClient,
            'prix' => $prix,
        ]);

        $this->addFlash('success', sprintf(
            'Tarif de %s $ défini avec succès pour "%s" (%s) en zone %s !',
            number_format($prix, 2, ',', ' '),
            $produit->getNom(),
            $conditionnement->getLibelle(),
            $region ? $region->getNom() : 'Nationale'
        ));

        return $this->redirectToRoute('app_tarif_index');
    }

    #[Route('/{id}/toggle', name: 'app_tarif_toggle', methods: ['POST'])]
    public function toggle(Tarif $tarif, EntityManagerInterface $em, AuditService $audit): Response
    {
        $tarif->setActif(!$tarif->isActif());
        $em->flush();

        $audit->log('tarif', 'TOGGLE', $tarif->getId(), null, ['actif' => $tarif->isActif()]);
        $this->addFlash('info', 'Le statut du tarif a été mis à jour.');
        return $this->redirectToRoute('app_tarif_index');
    }

    #[Route('/{id}/supprimer', name: 'app_tarif_delete', methods: ['POST'])]
    public function delete(Tarif $tarif, EntityManagerInterface $em, AuditService $audit): Response
    {
        $id = $tarif->getId();
        $audit->log('tarif', 'DELETE', $id, ['prix' => $tarif->getPrix()]);
        $em->remove($tarif);
        $em->flush();

        $this->addFlash('warning', 'Le tarif a été supprimé de la grille.');
        return $this->redirectToRoute('app_tarif_index');
    }

    #[Route('/api/prix-applicable', name: 'api_tarif_prix_applicable', methods: ['GET'])]
    public function getPrixApplicable(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $produitId = $request->query->get('produit_id');
        $conditionnementId = $request->query->get('conditionnement_id');
        $typeClient = $request->query->get('type_client', 'GROSSISTE');
        $grossisteId = $request->query->get('grossiste_id');
        $detailantId = $request->query->get('detailant_id');
        $regionId = $request->query->get('region_id');
        $zoneId = $request->query->get('zone_id');

        if (!$produitId || !$conditionnementId) {
            return new JsonResponse(['prix' => 0.0, 'source' => 'Invalide'], 400);
        }

        $produit = $em->getRepository(Produit::class)->find($produitId);
        $conditionnement = $em->getRepository(Conditionnement::class)->find($conditionnementId);

        if (!$produit || !$conditionnement) {
            return new JsonResponse(['prix' => 0.0, 'source' => 'Non trouvé'], 404);
        }

        // Si le client est spécifié, déduire sa région et sa zone
        $region = null;
        $zone = null;
        if ($grossisteId) {
            $grossiste = $em->getRepository(Grossiste::class)->find($grossisteId);
            if ($grossiste) {
                $region = $grossiste->getRegion();
                $zone = $grossiste->getZone();
            }
        } elseif ($detailantId) {
            $detailant = $em->getRepository(Detailant::class)->find($detailantId);
            if ($detailant) {
                $region = $detailant->getRegion();
                $zone = $detailant->getZone();
            }
        }

        if (!$region && $regionId) {
            $region = $em->getRepository(Region::class)->find($regionId);
        }
        if (!$zone && $zoneId) {
            $zone = $em->getRepository(ZoneCommerciale::class)->find($zoneId);
        }

        // 1. Recherche tarif spécifique par Zone
        if ($zone) {
            $tarifZone = $em->getRepository(Tarif::class)->findOneBy([
                'produit' => $produit,
                'conditionnement' => $conditionnement,
                'zone' => $zone,
                'typeClient' => $typeClient,
                'actif' => true
            ]);
            if ($tarifZone) {
                return new JsonResponse([
                    'prix' => (float) $tarifZone->getPrix(),
                    'source' => 'Tarif Zone: ' . $zone->getNom(),
                    'zone' => $zone->getNom(),
                    'devise' => '$'
                ]);
            }
        }

        // 2. Recherche tarif spécifique par Région
        if ($region) {
            $tarifRegion = $em->getRepository(Tarif::class)->findOneBy([
                'produit' => $produit,
                'conditionnement' => $conditionnement,
                'region' => $region,
                'typeClient' => $typeClient,
                'actif' => true
            ]);
            if ($tarifRegion) {
                return new JsonResponse([
                    'prix' => (float) $tarifRegion->getPrix(),
                    'source' => 'Tarif Région: ' . $region->getNom(),
                    'region' => $region->getNom(),
                    'devise' => '$'
                ]);
            }
        }

        // 3. Fallback Tarif National / Par défaut
        $tarifNational = $em->getRepository(Tarif::class)->findOneBy([
            'produit' => $produit,
            'conditionnement' => $conditionnement,
            'typeClient' => $typeClient,
            'actif' => true
        ]);

        if ($tarifNational) {
            return new JsonResponse([
                'prix' => (float) $tarifNational->getPrix(),
                'source' => 'Tarif de Référence',
                'region' => 'Nationale',
                'devise' => '$'
            ]);
        }

        return new JsonResponse([
            'prix' => 0.0,
            'source' => 'Non défini',
            'devise' => '$'
        ]);
    }
}
