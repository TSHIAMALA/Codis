<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion des comptes désactivés ou en attente d'activation.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur || $user->isActif()) {
            return;
        }

        if ($user->getRolesEntities()->isEmpty()) {
            throw new CustomUserMessageAccountStatusException('Votre demande d\'accès est en attente de validation par un administrateur.');
        }

        throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Contactez votre administrateur.');
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
    }
}
