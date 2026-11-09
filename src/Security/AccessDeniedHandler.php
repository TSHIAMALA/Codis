<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

/**
 * Affiche une page "Accès refusé" claire (avec le menu) au lieu de l'erreur 403 brute.
 */
class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private Environment $twig) {}

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        if ($request->getPreferredFormat() === 'json' || $request->isXmlHttpRequest()) {
            return new JsonResponse(['error' => 'Accès refusé'], Response::HTTP_FORBIDDEN);
        }

        return new Response($this->twig->render('security/access_denied.html.twig', [
            'message' => $accessDeniedException->getMessage(),
        ]), Response::HTTP_FORBIDDEN);
    }
}
