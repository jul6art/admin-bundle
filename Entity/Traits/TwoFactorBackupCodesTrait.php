<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Entity\Traits;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AdminBundle\TwoFactor\BackupCodeGenerator;

/**
 * The backup codes of the second factor, on the application's own `User` — one JSON column of
 * hashes, beside `TwoFactorTrait`'s two.
 *
 * ```php
 * class User implements UserInterface, TwoFactorAwareInterface, BackupCodeAwareInterface
 * {
 *     use TwoFactorTrait;
 *     use TwoFactorBackupCodesTrait;   // totp_backup_codes — then a migration
 * }
 * ```
 *
 * ⚠️ Keep `totpBackupCodes` out of an audited diff and out of any report or export, as `totpSecret`:
 * they are hashes, but a diff that shows them changing tells when a code was used.
 */
trait TwoFactorBackupCodesTrait
{
    /**
     * @var list<string>|null SHA-256 hashes of the codes still unused
     */
    #[ORM\Column(name: 'totp_backup_codes', type: Types::JSON, nullable: true)]
    private ?array $totpBackupCodes = null;

    public function isBackupCode(string $code): bool
    {
        $hash = BackupCodeGenerator::hash($code);

        return array_any($this->totpBackupCodes ?? [], static fn (string $stored): bool => hash_equals($stored, $hash));
    }

    public function invalidateBackupCode(string $code): void
    {
        $hash = BackupCodeGenerator::hash($code);
        $this->totpBackupCodes = array_values(array_filter($this->totpBackupCodes ?? [], static fn (string $stored): bool => !hash_equals($stored, $hash)));
    }

    /**
     * @param list<string> $codes
     */
    public function replaceBackupCodes(array $codes): static
    {
        $this->totpBackupCodes = array_map(BackupCodeGenerator::hash(...), $codes);

        return $this;
    }

    public function countBackupCodes(): int
    {
        return \count($this->totpBackupCodes ?? []);
    }

    public function clearBackupCodes(): static
    {
        $this->totpBackupCodes = null;

        return $this;
    }
}
