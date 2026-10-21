<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The TOTP enrolment of an account: a secret set aside as pending, shown once (QR code and manual
 * entry), activated only by a first correct code; turned off by a current code; reset by the
 * application. Each change of state is dispatched as a `TwoFactorEvent`.
 *
 * ⚠️ The entity comes from the security token, so the unit of work already tracks it: a `flush()`
 * is all it takes, exactly as on the appearance screen.
 */
final readonly class TotpEnrolment
{
    public function __construct(
        private TotpAuthenticatorInterface $totp,
        private EntityManagerInterface $entityManager,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    /** Sets a pending secret when the account has none: nothing changes for one already enrolled. */
    public function prepare(TwoFactorAwareInterface $account): void
    {
        if (null === $account->getTotpAuthenticationConfiguration()) {
            $account->startTotpEnrolment($this->totp->generateSecret());
            $this->entityManager->flush();
        }
    }

    /** The QR code to scan, an SVG in a `data:` URI: no external resource, nothing the CSP refuses. */
    public function qrCode(TwoFactorAwareInterface $account): string
    {
        return new Builder(writer: new SvgWriter(), data: $this->totp->getQRContent($account), size: 220, margin: 8)->build()->getDataUri();
    }

    /** The secret in groups of four, for typing it by hand. */
    public function readableSecret(TwoFactorAwareInterface $account): string
    {
        return implode(' ', str_split((string) $account->getTotpAuthenticationConfiguration()?->getSecret(), 4));
    }

    public function isValidCode(TwoFactorAwareInterface $account, string $code): bool
    {
        return null !== $account->getTotpAuthenticationConfiguration() && $this->totp->checkCode($account, $code);
    }

    /** Activates the pending secret if the first code is right. */
    public function confirm(TwoFactorAwareInterface $account, string $code): bool
    {
        if (!$account->isTotpEnrolmentPending() || !$this->isValidCode($account, $code)) {
            return false;
        }

        $account->confirmTotp(new \DateTimeImmutable());
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::ENABLED);

        return true;
    }

    /**
     * Turns it off — on a CURRENT code only: a session left open must not be enough to strip the
     * account of its second factor.
     */
    public function disable(TwoFactorAwareInterface $account, string $code): bool
    {
        if (!$account->isTotpAuthenticationEnabled() || !$this->isValidCode($account, $code)) {
            return false;
        }

        $this->reset($account);

        return true;
    }

    /** The application's way out — an account that lost its phone, reset by an administrator. */
    public function reset(TwoFactorAwareInterface $account): void
    {
        $account->resetTotp();
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::DISABLED);
    }
}
