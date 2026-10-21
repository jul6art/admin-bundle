<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\Entity\Traits\TwoFactorTrait;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;

/**
 * Les trois états du secret : rien, en attente (il ne protège encore rien), actif.
 */
#[CoversTrait(TwoFactorTrait::class)]
final class TwoFactorTraitTest extends TestCase
{
    public function testAPendingSecretProtectsNothingUntilItIsConfirmed(): void
    {
        $account = new Account()->setEmail('ada@example.test');
        self::assertFalse($account->isTotpAuthenticationEnabled());
        self::assertNull($account->getTotpAuthenticationConfiguration());

        $account->startTotpEnrolment('JBSWY3DPEHPK3PXP');
        self::assertTrue($account->isTotpEnrolmentPending());
        self::assertFalse($account->isTotpAuthenticationEnabled(), 'En attente, le secret ne demande encore aucun code.');

        $account->confirmTotp(new \DateTimeImmutable('2026-10-22 09:00'));
        self::assertFalse($account->isTotpEnrolmentPending());
        self::assertTrue($account->isTotpAuthenticationEnabled());
        self::assertEquals(new \DateTimeImmutable('2026-10-22 09:00'), $account->getTotpEnabledAt());

        $account->resetTotp();
        self::assertFalse($account->isTotpAuthenticationEnabled());
        self::assertNull($account->getTotpAuthenticationConfiguration());
        self::assertNull($account->getTotpEnabledAt());
    }

    /**
     * SHA-1, 30 secondes, six chiffres : les seuls paramètres que toutes les applications
     * d'authentification lisent correctement — un autre algorithme passe le scan et échoue au code.
     */
    public function testTheConfigurationIsTheOneEveryAuthenticatorAppReads(): void
    {
        $configuration = new Account()->setEmail('ada@example.test')->startTotpEnrolment('JBSWY3DPEHPK3PXP')->getTotpAuthenticationConfiguration();

        self::assertNotNull($configuration);
        self::assertSame('JBSWY3DPEHPK3PXP', $configuration->getSecret());
        self::assertSame(TotpConfiguration::ALGORITHM_SHA1, $configuration->getAlgorithm());
        self::assertSame(30, $configuration->getPeriod());
        self::assertSame(6, $configuration->getDigits());
    }

    public function testTheAppShowsTheAccountByItsIdentifier(): void
    {
        self::assertSame('ada@example.test', new Account()->setEmail('ada@example.test')->getTotpAuthenticationUsername());
    }
}
