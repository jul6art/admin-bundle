<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Contract;

use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

/**
 * An account that can carry the optional two-factor authentication of `admin.two_factor`.
 *
 * `TwoFactorTrait` implements all of it on the application's own `User`; this contract only adds,
 * to scheb's `TwoFactorInterface`, the enrolment states the bundle's screens read and write.
 *
 * The life of the secret: **pending** once `startTotpEnrolment()` has set it (it protects nothing
 * yet), **enabled** once a first correct code has confirmed it, back to nothing on `resetTotp()`.
 */
interface TwoFactorAwareInterface extends TwoFactorInterface
{
    /** Sets a new secret, not yet active: the account still signs in without a code. */
    public function startTotpEnrolment(string $secret): static;

    /** Activates the pending secret — called once a first code proved the authenticator app has it. */
    public function confirmTotp(\DateTimeImmutable $at): static;

    public function isTotpEnrolmentPending(): bool;

    public function getTotpEnabledAt(): ?\DateTimeImmutable;

    /** Forgets the secret: the account signs in with its password alone again. */
    public function resetTotp(): static;
}
