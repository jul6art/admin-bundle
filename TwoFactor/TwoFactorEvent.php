<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * What happened to an account's second factor — the bundle writes no audit trail of its own, an
 * application that keeps one listens to these names.
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
    /** New backup codes replaced the previous ones (`admin.two_factor.backup_codes`). */
    public const string BACKUP_CODES_GENERATED = 'admin.two_factor.backup_codes_generated';
    /**
     * A backup code stood in for the authenticator app — at sign-in, or on the account screen. Often
     * the sign of a lost phone: an audit trail records it.
     */
    public const string BACKUP_CODE_USED = 'admin.two_factor.backup_code_used';

    public function __construct(
        public readonly TwoFactorAwareInterface $account,
    ) {
    }
}
