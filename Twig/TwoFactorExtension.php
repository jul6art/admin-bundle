<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Twig;

use Twig\Attribute\AsTwigFunction;

/**
 * What the sign-in code page needs to know of `admin.two_factor` — scheb renders it, and passes none
 * of the bundle's settings. Registered only when the second factor is offered.
 */
final readonly class TwoFactorExtension
{
    public function __construct(
        private bool $backupCodes = false,
    ) {
    }

    /** Whether the code page offers to type a backup code instead (`admin.two_factor.backup_codes`). */
    #[AsTwigFunction(name: 'admin_two_factor_backup_codes')]
    public function backupCodes(): bool
    {
        return $this->backupCodes;
    }
}
