<?php

namespace App\Controller\Api;

use App\Entity\Livraison;
use App\Exception\OperationImpossibleException;
use App\Service\LivraisonService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
class ApiLivraisonsController extends AbstractController
{
    #[Route('/livraisons', name: 'api_livraisons_list', methods: ['GET'])]
    public function list(EntityManagerInterface $em): JsonResponse
    {
        $livraisons = $em->getRepository(Livraison::class)->findBy([], ['id' => 'DESC'], 50);
        $res = [];
        foreach ($livraisons as $l) {
            $res[] = [
                'id' => $l->getId(),
                'numero' => $l->getNumero(),
                'commandeNumero' => $l->getCommande() ? $l->getCommande()->getNumero() : '',
                'clientNom' => $l->getCommande() ? $l->getCommande()->getClientNom() : '',
                'depot' => $l->getDepot() ? $l->getDepot()->getNom() : '',
                'datePrevue' => $l->getDatePrevue() ? $l->getDatePrevue()->format('Y-m-d') : '',
                'dateLivraison' => $l->getDateLivraison() ? $l->getDateLivraison()->format('Y-m-d H:i') : '',
                'statut' => $l->getStatut(),
            ];
        }
        return new JsonResponse($res);
    }

    #[IsGranted('ROLE_STAFF')]
    #[Route('/livraisons/{id}/confirmer', name: 'api_livraisons_confirmer', methods: ['POST'])]
    public function confirmer(int $id, EntityManagerInterface $em, LivraisonService $livraisonService): JsonResponse
    {
        $l = $em->getRepository(Livraison::class)->find($id);
        if (!$l) return new JsonResponse(['error' => 'Livraison non trouvée'], 404);

        try {
            $livraisonService->confirmerReception($l);
        } catch (OperationImpossibleException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 409);
        }

        return new JsonResponse(['success' => true, 'statut' => $l->getStatut()]);
    }
}