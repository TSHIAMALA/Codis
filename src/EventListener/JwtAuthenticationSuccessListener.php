<?php

namespace App\EventListener;

use App\Entity\Utilisateur;
use App\Service\ApiUserSerializer;
use Lexik\Bundle\JWTAuthenticationBundle\Event\AuthenticationSuccessEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Events;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Complète la réponse de POST /api/login : {token, user: {...}} (format attendu par l'app mobile).
 */
#[AsEventListener(event: Events::AUTHENTICATION_SUCCESS)]
class JwtAuthenticationSuccessListener
{
    public function __construct(private ApiUserSerializer $serializer) {}

    public function __invoke(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof Utilisateur) {
            return;
        }

        $data = $event->getData();
        $data['user'] = $this->serializer->serialize($user);
        $event->setData($data);
    }
}
