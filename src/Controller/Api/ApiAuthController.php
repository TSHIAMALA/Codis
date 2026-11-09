<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Service\ApiUserSerializer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/login est géré par le firewall "api_login" (json_login + JWT Lexik),
 * la réponse est complétée par App\EventListener\JwtAuthenticationSuccessListener.
 */
#[Route('/api')]
class ApiAuthController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Cette méthode est interceptée par le firewall "api_login" (json_login).');
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(ApiUserSerializer $serializer): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return new JsonResponse($serializer->serialize($user));
    }
}
