<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Controller\Security;

use Jul6Art\AdminBundle\Account\AccountSpaces;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Sign-in and sign-out (ADR-0001, T20 lot 3).
 *
 * The card carries the product's logo and name (`admin.branding`); its sign-up link appears only when
 * `admin.routes.register` names a route. No route is declared: the application names them
 * (`admin_security_login`, `admin_security_logout`), a second space with `_auth_space` in their defaults.
 */
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly AccountSpaces $spaces,
    ) {
    }

    #[IsGranted('PUBLIC_ACCESS')]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // Already signed in: a space that has a home sends the visitor there, rather than ask again.
        $home = $this->spaces->redirectAuthenticatedTo($request);
        if (null !== $home && $this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute($home);
        }

        return $this->render($this->spaces->template($request, 'login'), [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ] + $this->spaces->context($request));
    }

    /**
     * Intercepted by the firewall: the body never runs. The access decision IS the firewall's `logout` key, POST only.
     */
    public function logout(): never
    {
        throw new \LogicException('Intercepted by the firewall: this method is never run. If it is, the route left the firewall\'s logout key.');
    }
}
