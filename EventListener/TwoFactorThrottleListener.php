<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\EventListener;

use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Jul6Art\AdminBundle\TwoFactor\TwoFactorEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The code does not fall to brute force: the sign-in throttling only counts PASSWORDS, and six
 * digits fall in a million attempts. Five failures per quarter of an hour and per account (the
 * `admin_two_factor` limiter): beyond, even the right code is refused before it is read. Every
 * failure is dispatched as `TwoFactorEvent::FAILED`; a success resets the counter.
 *
 * The refusal message is `two_factor.throttled`, in the `SchebTwoFactorBundle` domain the sign-in
 * template translates its errors in — the application adds that key to its catalogue.
 */
final readonly class TwoFactorThrottleListener
{
    public function __construct(
        private RateLimiterFactoryInterface $limiter,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public function onAttempt(TwoFactorAuthenticationEvent $event): void
    {
        if (0 === $this->limiter->create($this->key($event))->consume(0)->getRemainingTokens()) {
            throw new CustomUserMessageAuthenticationException('two_factor.throttled');
        }
    }

    public function onFailure(TwoFactorAuthenticationEvent $event): void
    {
        $this->limiter->create($this->key($event))->consume();

        $account = $event->getToken()->getUser();
        if ($account instanceof TwoFactorAwareInterface) {
            $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::FAILED);
        }
    }

    public function onComplete(TwoFactorAuthenticationEvent $event): void
    {
        $this->limiter->create($this->key($event))->reset();
    }

    private function key(TwoFactorAuthenticationEvent $event): string
    {
        return '2fa-'.$event->getToken()->getUserIdentifier();
    }
}
