<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Vérifie le jeton CSRF (champ "_token", id "app") de tout POST de l'interface web.
 * Les formulaires l'incluent via {{ include('_csrf.html.twig') }}.
 * Exclus : l'API (JWT, sans session) et /login (protégé par form_login).
 */
class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    public const TOKEN_ID = 'app';
    public const FIELD = '_token';

    public function __construct(private CsrfTokenManagerInterface $csrfTokenManager) {}

    public static function getSubscribedEvents(): array
    {
        // Après le routeur (32) et avant le firewall (8) : inutile d'authentifier une requête rejetée.
        return [KernelEvents::REQUEST => ['onKernelRequest', 16]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->isMethod('POST') || $this->isExcluded($request)) {
            return;
        }

        $token = new CsrfToken(self::TOKEN_ID, (string) $request->request->get(self::FIELD));
        if ($this->csrfTokenManager->isTokenValid($token)) {
            return;
        }

        if ($request->hasSession()) {
            $request->getSession()->getFlashBag()->add('danger', 'Votre session a expiré ou le formulaire n\'est plus valide. Merci de réessayer.');
        }
        $event->setResponse(new RedirectResponse($this->safeReturnUrl($request)));
    }

    private function safeReturnUrl(Request $request): string
    {
        $home = $request->getSchemeAndHttpHost() . $request->getBaseUrl() . '/';
        $referer = (string) $request->headers->get('referer');

        return str_starts_with($referer, $request->getSchemeAndHttpHost() . '/') ? $referer : $home;
    }

    private function isExcluded(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, '/api/') || $path === '/login' || str_starts_with($path, '/_');
    }
}
