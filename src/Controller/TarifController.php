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
use App\Pagination\Page;
use App\Service\AuditService;
use App\Service\TarifResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/tarifs')]
class TarifController extends AbstractController
{
    #[IsGranted('ROLE_ADMIN_PAYS')]
    #[Route('', name: 'app_tarif_index', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $regionFilter = $request->query->get('region_id');
        $produitFilter = $request->query->get('produit_id');
        $typeClientFilter = $request->query->get('type_client');

        $qb = $em->createQueryBuilder()
            ->select('t', 'p', 'c', 'r', 'z')
            ->from(Tarif::class, 't')
            ->join('t.produit', 'p')
            ->join('t.conditionnement', 'c')
            ->leftJoin('t.region', 'r')
            ->leftJoin('t.zone', 'z')
            ->orderBy('t.dateDebut', 'DESC')
            ->addOrderBy('t.id', 'DESC');
        if (!empty($regionFilter)) {
            $qb->andWhere('t.region = :region')->setParameter('region', (int) $regionFilter);
        }
        if (!empty($produitFilter)) {
            $qb->andWhere('t.produit = :produit')->setParameter('produit', (int) $produitFilter);
        }
        if (!empty($typeClientFilter)) {
            $qb->andWhere('t.typeClient = :type')->setParameter('type', $typeClientFilter);
        }
        $tarifs = Page::depuisRequete($qb, $request);
        $produits = $em->createQueryBuilder()
            ->select('p', 'cond')->from(Produit::class, 'p')->leftJoin('p.conditionnements', 'cond')
            ->where('p.actif = true')->orderBy('p.id')->addOrderBy('cond.id')
            ->getQuery()->getResult();
        $regions = $em->getRepository(Region::class)->findAll();
        $zones = $em->getRepository(ZoneCommerciale::class)->findBy(['actif' => true]);
        $paysList = $em->getRepository(Pays::class)->findAll();
        $conditionnements = $em->getRepository(Conditionnement::class)->findAll();

        // Indicateurs calculés par la base sur l'ensemble de la grille
        $totalTarifs = $tarifs->total;
        $regionsCount = count($regions);
        $indicateurs = $em->createQueryBuilder()
            ->select('COUNT(t.id) AS actifs', 'COUNT(DISTINCT IDENTITY(t.produit)) AS produits')
            ->from(Tarif::class, 't')
            ->where('t.actif = true')
            ->getQuery()->getSingleResult();
        $tarifsActifs = (int) $indicateurs['actifs'];
        $produitsTarifesCount = (int) $indicateurs['produits'];

        // Tous les tarifs régionaux actifs en une requête, indexés pour la grille (tarif de région prioritaire sur un tarif de zone)
        $prixRegionaux = [];
        foreach ($em->createQueryBuilder()
            ->select('IDENTITY(t.produit) AS produit', 'IDENTITY(t.conditionnement) AS cond', 'IDENTITY(t.region) AS region', 't.typeClient AS type', 't.prix AS prix', 'CASE WHEN t.zone IS NULL THEN 0 ELSE 1 END AS HIDDEN avecZone')
            ->from(Tarif::class, 't')
            ->where('t.actif = true AND t.region IS NOT NULL')
            ->orderBy('avecZone', 'DESC')
            ->getQuery()->getArrayResult() as $t) {
            // Tri « avec zone » d'abord : le tarif de région (sans zone), lu en dernier, l'emporte
            $prixRegionaux[$t['produit']][$t['cond']][$t['region']][$t['type']] = (float) $t['prix'];
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
                    $matrice[$pId]['lignes'][$condId]['prix_par_region'][$rId] = [
                        'grossiste' => $prixRegionaux[$pId][$condId][$rId]['GROSSISTE'] ?? null,
                        'detaillant' => $prixRegionaux[$pId][$condId][$rId]['DETAILLANT'] ?? null,
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
            'produitsTarifesCount' => $produitsTarifesCount,
            'currentRegion' => $regionFilter,
            'currentProduit' => $produitFilter,
            'currentTypeClient' => $typeClientFilter,
        ]);
    }

    #[IsGranted('ROLE_ADMIN_PAYS')]
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

    #[IsGranted('ROLE_ADMIN_PAYS')]
    #[Route('/{id}/toggle', name: 'app_tarif_toggle', methods: ['POST'])]
    public function toggle(Tarif $tarif, EntityManagerInterface $em, AuditService $audit): Response
    {
        $tarif->setActif(!$tarif->isActif());
        $em->flush();

        $audit->log('tarif', 'TOGGLE', $tarif->getId(), null, ['actif' => $tarif->isActif()]);
        $this->addFlash('info', 'Le statut du tarif a été mis à jour.');
        return $this->redirectToRoute('app_tarif_index');
    }

    #[IsGranted('ROLE_ADMIN_PAYS')]
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

    #[IsGranted('ROLE_AGENT_COMMERCIAL')]
    #[Route('/api/prix-applicable', name: 'api_tarif_prix_applicable', methods: ['GET'])]
    public function getPrixApplicable(Request $request, EntityManagerInterface $em, TarifResolver $tarifResolver): JsonResponse
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

        $grossiste = $grossisteId ? $em->getRepository(Grossiste::class)->find($grossisteId) : null;
        $detailant = (!$grossiste && $detailantId) ? $em->getRepository(Detailant::class)->find($detailantId) : null;
        $region = $regionId ? $em->getRepository(Region::class)->find($regionId) : null;
        $zone = $zoneId ? $em->getRepository(ZoneCommerciale::class)->find($zoneId) : null;

        $tarif = $tarifResolver->resoudre($produit, $conditionnement, $typeClient, $grossiste, $detailant, $region, $zone);

        return new JsonResponse($tarif ?? [
            'prix' => 0.0,
            'source' => 'Non défini',
            'devise' => '$'
        ]);
    }
}
