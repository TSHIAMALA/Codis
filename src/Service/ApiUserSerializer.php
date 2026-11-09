<?php

namespace App\Service;

use App\Entity\AgentCommercial;
use App\Entity\Delegue;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Profil utilisateur renvoyé à l'application mobile (login et /api/profile).
 */
class ApiUserSerializer
{
    public function __construct(private EntityManagerInterface $em) {}

    public function serialize(Utilisateur $user): array
    {
        $agent = $this->em->getRepository(AgentCommercial::class)->findOneBy(['utilisateur' => $user, 'actif' => true]);
        $delegue = $this->em->getRepository(Delegue::class)->findOneBy(['utilisateur' => $user, 'actif' => true]);
        $pays = $user->getPays();

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'nom' => $user->getNom(),
            'prenom' => $user->getPrenom(),
            'nomComplet' => $user->getNomComplet(),
            'email' => $user->getEmail(),
            'telephone' => $user->getTelephone(),
            'pays' => $pays ? ['id' => $pays->getId(), 'nom' => $pays->getNom(), 'code' => $pays->getCode(), 'devise' => $pays->getDevise()] : null,
            'roles' => $user->getRoles(),
            'agentCommercialId' => $agent?->getId(),
            'typeAgent' => $agent?->getTypeAgent(),
            'delegueId' => $delegue?->getId(),
        ];
    }
}
