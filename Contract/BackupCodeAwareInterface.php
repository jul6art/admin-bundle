<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Contract;

use Scheb\TwoFactorBundle\Model\BackupCodeInterface;

/**
 * An account that keeps backup codes for its second factor — single-use codes that stand in for the
 * authenticator app when the phone is lost.
 *
 * `TwoFactorBackupCodesTrait` implements all of it. scheb only reads `isBackupCode()` and
 * `invalidateBackupCode()` at sign-in; the three methods below are what the bundle's screen writes.
 *
 * ⚠️ The codes are stored as hashes: `replaceBackupCodes()` takes the codes in clear, once, and they
 * can never be read back — show them to the account when they are generated, never afterwards.
 */
interface BackupCodeAwareInterface extends BackupCodeInterface
{
    /**
     * Replaces every backup code: the previous ones stop working.
     *
     * @param list<string> $codes the codes in clear, as `BackupCodeGenerator` drew them
     */
    public function replaceBackupCodes(array $codes): static;

    /** How many codes are left. */
    public function countBackupCodes(): int;

    public function clearBackupCodes(): static;
}
