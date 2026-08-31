<?php

namespace App\Controller\Api;

use App\Entity\Produit;
use App\Entity\Tarif;
use App\Entity\Conditionnement;
use App\Entity\Pays;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiCatalogueController extends AbstractController
{
    #[Route('/produits', name: 'api_produits_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $paysId = $request->query->get('pays_id');
        $produits = $em->getRepository(Produit::class)->findBy(['actif' => true]);

        $res = [];
        foreach ($produits as $p) {
            $conds = [];
            foreach ($p->getConditionnements() as $c) {
                // Find active tarif
                $tarifCriteria = ['produit' => $p, 'conditionnement' => $c, 'actif' => true];
                if ($paysId) {
                    $pays = $em->getRepository(Pays::class)->find($paysId);
                    if ($pays) $tarifCriteria['pays'] = $pays;
                }
                $tarif = $em->getRepository(Tarif::class)->findOneBy($tarifCriteria);

                $conds[] = [
                    'id' => $c->getId(),
                    'libelle' => $c->getLibelle(),
                    'niveau' => $c->getNiveau(),
                    'uniteReference' => $c->getUniteReference(),
                    'quantiteConversion' => (float) $c->getQuantiteConversion(),
                    'prix' => $tarif ? (float) $tarif->getPrix() : 0.0,
                ];
            }

            $res[] = [
                'id' => $p->getId(),
                'code' => $p->getCode(),
                'nom' => $p->getNom(),
                'marque' => $p->getMarque() ? $p->getMarque()->getNom() : '',
                'categorie' => $p->getCategorie() ? $p->getCategorie()->getLibelle() : '',
                'description' => $p->getDescription(),
                'conditionnements' => $conds,
            ];
        }

        return new JsonResponse($res);
    }
}