<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\EventListener;

use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Jul6Art\AdminBundle\Controller\TwoFactorController;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * `admin.two_factor.required: true` — an account that has not enabled the second factor reaches
 * the enrolment screen and nothing else: it can still sign out, and reach the `open_routes`. A
 * background request (XHR) or an API call gets a 403 rather than a redirect it cannot follow.
 *
 * Every import of the screen is open — an application with several areas imports it once per area —
 * and the redirect goes to `admin.two_factor.enrolment_route`.
 *
 * Registered only when the option is on, on `kernel.request` at priority 7: after the firewall
 * (8), which fills the token storage.
 */
final readonly class TwoFactorEnforcementListener
{
    /**
     * @param list<string> $openRoutes the enrolment screen, the sign-out, and what the application adds
     */
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urls,
        private string $enrolmentRoute,
        private array $openRoutes,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $account = $token?->getUser();
        // Pendant la saisie du code (jeton de scheb), rien à imposer : c'est la connexion elle-même.
        if ($token instanceof TwoFactorTokenInterface || !$account instanceof TwoFactorAwareInterface || $account->isTotpAuthenticationEnabled()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $controller = $request->attributes->get('_controller');
        if ((\is_string($route) && ($route === $this->enrolmentRoute || \in_array($route, $this->openRoutes, true)))
            || (\is_string($controller) && str_starts_with($controller, TwoFactorController::class))) {
            return;
        }

        $event->setResponse($request->isXmlHttpRequest() || str_starts_with($request->getPathInfo(), '/api')
            ? new Response(status: Response::HTTP_FORBIDDEN)
            : new RedirectResponse($this->urls->generate($this->enrolmentRoute)));
    }
}
