<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Jul6Art\AdminBundle\Contract\BackupCodeAwareInterface;
use Jul6Art\AdminBundle\Contract\TwoFactorAwareInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * The TOTP enrolment of an account: a secret set aside as pending, shown once (QR code and manual
 * entry), activated only by a first correct code; turned off by a current code; reset by the
 * application. Each change of state is dispatched as a `TwoFactorEvent`.
 *
 * With `admin.two_factor.backup_codes`, an account that carries them (`BackupCodeAwareInterface`)
 * also gets single-use backup codes: drawn at activation, redrawn on demand, and accepted wherever a
 * current code is — so an account that lost its phone can still turn the second factor off.
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
        private BackupCodeGenerator $generator = new BackupCodeGenerator(),
        private bool $backupCodes = false,
    ) {
    }

    /** Whether this account gets backup codes: the application offers them, and its `User` keeps them. */
    public function offersBackupCodes(TwoFactorAwareInterface $account): bool
    {
        return $this->backupCodes && $account instanceof BackupCodeAwareInterface;
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

    /**
     * A current code from the app — or, when the account has them, a backup code, which is then
     * spent: what an enabled account proves itself with on its own screen.
     */
    public function verify(TwoFactorAwareInterface $account, string $code): bool
    {
        if ($this->isValidCode($account, $code)) {
            return true;
        }

        if (!$this->offersBackupCodes($account) || !$account instanceof BackupCodeAwareInterface || !$account->isBackupCode($code)) {
            return false;
        }

        $account->invalidateBackupCode($code);
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::BACKUP_CODE_USED);

        return true;
    }

    /**
     * Draws a new set of backup codes and returns them IN CLEAR — the only time they exist so: the
     * account stores their hashes, and the caller shows them once.
     *
     * @return list<string>
     */
    public function generateBackupCodes(TwoFactorAwareInterface $account): array
    {
        if (!$this->offersBackupCodes($account) || !$account instanceof BackupCodeAwareInterface) {
            return [];
        }

        $codes = $this->generator->generate();
        $account->replaceBackupCodes($codes);
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::BACKUP_CODES_GENERATED);

        return $codes;
    }

    /**
     * New backup codes, on a current code (or one of the old backup codes): a session left open must
     * not be enough to read a fresh set.
     *
     * @return list<string>|null null when the code is wrong
     */
    public function regenerateBackupCodes(TwoFactorAwareInterface $account, string $code): ?array
    {
        if (!$account->isTotpAuthenticationEnabled() || !$this->offersBackupCodes($account) || !$this->verify($account, $code)) {
            return null;
        }

        return $this->generateBackupCodes($account);
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
     * Turns it off — on a CURRENT code, or a backup code: a session left open must not be enough to
     * strip the account of its second factor, and a lost phone must not lock it in.
     */
    public function disable(TwoFactorAwareInterface $account, string $code): bool
    {
        if (!$account->isTotpAuthenticationEnabled() || !$this->verify($account, $code)) {
            return false;
        }

        $this->reset($account);

        return true;
    }

    /** The application's way out — an account that lost its phone, reset by an administrator. */
    public function reset(TwoFactorAwareInterface $account): void
    {
        $account->resetTotp();
        if ($account instanceof BackupCodeAwareInterface) {
            $account->clearBackupCodes();
        }
        $this->entityManager->flush();
        $this->dispatcher->dispatch(new TwoFactorEvent($account), TwoFactorEvent::DISABLED);
    }
}
