<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\EventListener;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\TwoFactorFirewallContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\HttpUtils;

/**
 * An account signed in already that reaches the code page or its check — the code sent twice (Enter, then a click; a
 * password manager that submits too), the page opened again with "Back" — goes on where scheb sends it once the code
 * passes, instead of a 403. Both refuse it otherwise: the check, scheb's authenticator ("User is not in a two-factor
 * authentication process."); the page, the `access_control` rule the README asks for, `^/2fa` with
 * `IS_AUTHENTICATED_2FA_IN_PROGRESS`, which only a sign-in in progress holds.
 *
 * On `kernel.exception` at priority 2, before the firewall's exception listener (1), which would answer the 403. Only a
 * refusal on the code page or its check of the request's firewall, for a token that is neither anonymous nor still at
 * its code.
 */
final readonly class TwoFactorPassedListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private Security $security,
        private TwoFactorFirewallContext $firewalls,
        private HttpUtils $httpUtils,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $denied = $event->getThrowable();
        while (!$denied instanceof AccessDeniedException && null !== $denied) {
            $denied = $denied->getPrevious();
        }
        $token = $this->tokenStorage->getToken();
        if (null === $denied || null === $token?->getUser() || $token instanceof TwoFactorTokenInterface) {
            return;
        }
        $request = $event->getRequest();
        $firewall = $this->security->getFirewallConfig($request)?->getName();
        if (null === $firewall) {
            return;
        }
        try {
            $config = $this->firewalls->getFirewallConfig($firewall);
        } catch (\InvalidArgumentException) {
            return;
        }
        if (!$config->isAuthFormRequest($request) && !$this->httpUtils->checkRequestPath($request, $config->getCheckPath())) {
            return;
        }

        $event->setResponse($this->httpUtils->createRedirectResponse($request, $config->getDefaultTargetPath()));
    }
}
