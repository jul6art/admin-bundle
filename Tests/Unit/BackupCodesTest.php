<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\Entity\Traits\TwoFactorBackupCodesTrait;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\TwoFactor\BackupCodeGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversClass(BackupCodeGenerator::class)]
#[CoversTrait(TwoFactorBackupCodesTrait::class)]
final class BackupCodesTest extends TestCase
{
    public function testTenDistinctCodesThatAPersonReadsWithoutHesitating(): void
    {
        $codes = new BackupCodeGenerator()->generate();

        self::assertCount(10, $codes);
        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $code, 'Ni 0, ni O, ni 1, ni I.');
        }
    }

    public function testATypedCodeIsComparedWithoutItsCaseOrItsSeparator(): void
    {
        self::assertSame('K7QM2XW9RT', BackupCodeGenerator::normalize(' k7qm2 - xw9rt '));
        self::assertSame(BackupCodeGenerator::hash('K7QM2-XW9RT'), BackupCodeGenerator::hash('k7qm2xw9rt'));
    }

    /**
     * ⚠️ Stockés en empreintes : une fois montrés, les codes ne se relisent plus — ni à l'écran, ni dans
     * la base.
     */
    public function testTheAccountKeepsHashesAndSpendsEachCodeOnce(): void
    {
        $account = new Account()->replaceBackupCodes(['K7QM2-XW9RT', 'ABCDE-FGHJK']);

        self::assertSame(2, $account->countBackupCodes());
        self::assertStringNotContainsString('K7QM2', (string) json_encode(new \ReflectionProperty(Account::class, 'totpBackupCodes')->getValue($account)));
        self::assertTrue($account->isBackupCode('k7qm2xw9rt'));
        self::assertFalse($account->isBackupCode('ZZZZZ-ZZZZZ'));

        $account->invalidateBackupCode('K7QM2-XW9RT');

        self::assertFalse($account->isBackupCode('K7QM2-XW9RT'));
        self::assertTrue($account->isBackupCode('ABCDE-FGHJK'));
        self::assertSame(1, $account->countBackupCodes());
        self::assertSame(0, $account->clearBackupCodes()->countBackupCodes());
    }
}
