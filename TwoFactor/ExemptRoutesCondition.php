<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;

/**
 * The sign-ins that skip the code: the routes of `admin.two_factor.exempt_routes`.
 *
 * Its purpose is the development login link (`login_link` on a route that only exists in `dev`),
 * which walks the screens as any fixture account. Listing a route here takes the second factor off
 * every sign-in that lands on it — never list a route that exists in production.
 */
final readonly class ExemptRoutesCondition implements TwoFactorConditionInterface
{
    /**
     * @param list<string> $exemptRoutes
     */
    public function __construct(
        private array $exemptRoutes,
    ) {
    }

    #[\Override]
    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        return !\in_array($context->getRequest()->attributes->get('_route'), $this->exemptRoutes, true);
    }
}
