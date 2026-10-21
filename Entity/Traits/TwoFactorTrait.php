<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

/**
 * The two columns of the optional two-factor authentication, on the application's own `User`.
 *
 * ```php
 * #[ORM\Entity]
 * class User implements UserInterface, TwoFactorAwareInterface
 * {
 *     use TwoFactorTrait;
 * }
 * ```
 *
 * A trait for the reason `AppearancePreferencesTrait` is one: a bundle-shipped embeddable needs a
 * Doctrine mapping for the bundle's namespace, and those are switched off in this ecosystem.
 *
 * ## The secret is not a password
 *
 * A TOTP secret is shared with the authenticator app: it has to be read back to check a code, so it
 * cannot be hashed. Guard the database accordingly — and never print it outside the enrolment
 * screen, which shows it once, while it is still pending.
 *
 * ⚠️ The username in the authenticator app is `getUserIdentifier()` — the e-mail in every
 * application of this ecosystem. Overriding `getTotpAuthenticationUsername()` changes the label
 * the account sees in its app, not the secret.
 */
trait TwoFactorTrait
{
    /** Set at enrolment, it protects nothing while `totpEnabledAt` is empty. */
    #[ORM\Column(name: 'totp_secret', length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'totp_enabled_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $totpEnabledAt = null;

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret && null !== $this->totpEnabledAt;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->getUserIdentifier();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        // SHA-1, 30 seconds, six digits: the only parameters every authenticator app reads right.
        return null === $this->totpSecret ? null : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function startTotpEnrolment(string $secret): static
    {
        $this->totpSecret = $secret;
        $this->totpEnabledAt = null;

        return $this;
    }

    public function confirmTotp(\DateTimeImmutable $at): static
    {
        $this->totpEnabledAt = $at;

        return $this;
    }

    public function isTotpEnrolmentPending(): bool
    {
        return null !== $this->totpSecret && null === $this->totpEnabledAt;
    }

    public function getTotpEnabledAt(): ?\DateTimeImmutable
    {
        return $this->totpEnabledAt;
    }

    public function resetTotp(): static
    {
        $this->totpSecret = null;
        $this->totpEnabledAt = null;

        return $this;
    }
}
