<?php

namespace App\Controller\Api;

use App\Entity\Produit;
use App\Entity\AgentCommercial;
use App\Service\TarifResolver;
use App\Entity\Conditionnement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiCatalogueController extends AbstractController
{
    #[Route('/produits', name: 'api_produits_list', methods: ['GET'])]
    public function list(Request $request, EntityManagerInterface $em, TarifResolver $tarifResolver): JsonResponse
    {
        // Le prix affiché est celui que le serveur facturera : même grille, même type de client.
        // L'application mobile saisit des commandes de détaillants, d'où ce type par défaut.
        $typeClient = $request->query->get('type_client') === 'GROSSISTE' ? 'GROSSISTE' : 'DETAILLANT';
        $agent = $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $this->getUser(), 'actif' => true]);
        $region = $agent?->getRegion();
        $produits = $em->createQueryBuilder()
            ->select('p', 'c', 'm', 'cat')
            ->from(Produit::class, 'p')
            ->leftJoin('p.conditionnements', 'c')
            ->leftJoin('p.marque', 'm')
            ->leftJoin('p.categorie', 'cat')
            ->where('p.actif = true')
            ->orderBy('p.id')
            ->addOrderBy('c.niveau')
            ->getQuery()->getResult();

        $res = [];
        foreach ($produits as $p) {
            $conds = [];
            foreach ($p->getConditionnements() as $c) {
                $tarif = $tarifResolver->resoudre($p, $c, $typeClient, null, null, $region);

                $conds[] = [
                    'id' => $c->getId(),
                    'libelle' => $c->getLibelle(),
                    'niveau' => $c->getNiveau(),
                    'uniteReference' => $c->getUniteReference(),
                    'quantiteConversion' => (float) $c->getQuantiteConversion(),
                    'prix' => $tarif ? $tarif['prix'] : 0.0,
                    'prixSource' => $tarif ? $tarif['source'] : 'Aucun tarif',
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