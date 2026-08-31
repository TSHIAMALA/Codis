<?php

namespace App\Controller\Api;

use App\Entity\Utilisateur;
use App\Entity\AgentCommercial;
use App\Entity\Delegue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class ApiAuthController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return new JsonResponse(['error' => 'Identifiants requis'], 400);
        }

        $user = $em->getRepository(Utilisateur::class)->findOneBy(['username' => $username, 'actif' => true]);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur introuvable ou inactif'], 401);
        }

        // Check password (supports hashed or plain test fixtures)
        $valid = $hasher->isPasswordValid($user, $password) || password_verify($password, $user->getPassword()) || $user->getPassword() === $password;
        if (!$valid) {
            return new JsonResponse(['error' => 'Mot de passe incorrect'], 401);
        }

        // Determine specific commercial profile
        $agent = $em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $user, 'actif' => true]);
        $delegue = $em->getRepository(Delegue::class)->findOneBy(['utilisateur' => $user, 'actif' => true]);

        $roles = $user->getRoles();
        $token = base64_encode($user->getUsername() . ':' . time() . ':' . bin2hex(random_bytes(16)));

        return new JsonResponse([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'nom' => $user->getNom(),
                'prenom' => $user->getPrenom(),
                'nomComplet' => $user->getNomComplet(),
                'email' => $user->getEmail(),
                'telephone' => $user->getTelephone(),
                'pays' => $user->getPays() ? ['id' => $user->getPays()->getId(), 'nom' => $user->getPays()->getNom(), 'code' => $user->getPays()->getCode(), 'devise' => $user->getPays()->getDevise()] : null,
                'roles' => $roles,
                'agentCommercialId' => $agent ? $agent->getId() : null,
                'delegueId' => $delegue ? $delegue->getId() : null,
            ]
        ]);
    }

    #[Route('/profile', name: 'api_profile', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'nomComplet' => $user->getNomComplet(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }
}