<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * What happened to an account's second factor — the bundle writes no audit trail of its own, an
 * application that keeps one listens to these three names.
 *
 * ```php
 * #[AsEventListener(event: TwoFactorEvent::ENABLED)]
 * public function onEnabled(TwoFactorEvent $event): void { $this->audit->log('security.2fa_enabled', …); }
 * ```
 */
final class TwoFactorEvent extends Event
{
    /** A first correct code confirmed the enrolment. */
    public const string ENABLED = 'admin.two_factor.enabled';

    /** The account turned it off, or the application reset it (`TotpEnrolment::reset()`). */
    public const string DISABLED = 'admin.two_factor.disabled';

    /** A wrong code at sign-in — what an audit trail and a brute-force alarm read. */
    public const string FAILED = 'admin.two_factor.failed';

    public function __construct(
        public readonly TwoFactorAwareInterface $account,
    ) {
    }
}
