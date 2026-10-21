<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\TwoFactor\TotpEnrolment;
use Jul6Art\AdminBundle\TwoFactor\TwoFactorEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(TotpEnrolment::class)]
final class TotpEnrolmentTest extends TestCase
{
    /** @var list<string> */
    private array $dispatched = [];

    public function testPrepareSetsAPendingSecretOnceAndNeverReplacesIt(): void
    {
        $account = new Account()->setEmail('ada@example.test');
        $enrolment = $this->enrolment(validCode: '123456');

        $enrolment->prepare($account);
        $secret = $account->getTotpAuthenticationConfiguration()?->getSecret();
        $enrolment->prepare($account);

        self::assertSame('SECRETSECRETSECR', $secret);
        self::assertSame($secret, $account->getTotpAuthenticationConfiguration()?->getSecret(), 'Recharger la page ne change pas le QR code déjà scanné.');
        self::assertSame('SECR ETSE CRET SECR', $enrolment->readableSecret($account));
    }

    public function testOnlyARightFirstCodeEnablesIt(): void
    {
        $account = new Account()->setEmail('ada@example.test');
        $enrolment = $this->enrolment(validCode: '123456');
        $enrolment->prepare($account);

        self::assertFalse($enrolment->confirm($account, '000000'));
        self::assertTrue($account->isTotpEnrolmentPending());
        self::assertSame([], $this->dispatched);

        self::assertTrue($enrolment->confirm($account, '123456'));
        self::assertTrue($account->isTotpAuthenticationEnabled());
        self::assertSame([TwoFactorEvent::ENABLED], $this->dispatched);
    }

    /**
     * ⚠️ Une session laissée ouverte ne suffit pas à retirer le second facteur : il faut un code en cours.
     */
    public function testTurningItOffTakesACurrentCode(): void
    {
        $account = new Account()->setEmail('ada@example.test')->startTotpEnrolment('SECRETSECRETSECR')->confirmTotp(new \DateTimeImmutable());
        $enrolment = $this->enrolment(validCode: '123456');

        self::assertFalse($enrolment->disable($account, '000000'));
        self::assertTrue($account->isTotpAuthenticationEnabled());

        self::assertTrue($enrolment->disable($account, '123456'));
        self::assertFalse($account->isTotpAuthenticationEnabled());
        self::assertSame([TwoFactorEvent::DISABLED], $this->dispatched);
    }

    private function enrolment(string $validCode): TotpEnrolment
    {
        $totp = self::createStub(TotpAuthenticatorInterface::class);
        $totp->method('generateSecret')->willReturn('SECRETSECRETSECR');
        $totp->method('checkCode')->willReturnCallback(static fn (object $account, string $code): bool => $validCode === $code);

        $dispatcher = new EventDispatcher();
        foreach ([TwoFactorEvent::ENABLED, TwoFactorEvent::DISABLED] as $name) {
            $dispatcher->addListener($name, function () use ($name): void {
                $this->dispatched[] = $name;
            });
        }

        return new TotpEnrolment($totp, self::createStub(EntityManagerInterface::class), $dispatcher);
    }
}
