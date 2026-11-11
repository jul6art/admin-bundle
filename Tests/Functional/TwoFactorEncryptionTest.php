<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;
use Jul6Art\AdminBundle\Tests\Fixtures\TestKernel;
use Jul6Art\AdminBundle\TwoFactor\TotpSecretEncryption;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Jul6Art\CoreBundle\Security\Encryptor;

/**
 * Le secret TOTP est stocké CHIFFRÉ (1.39) : une copie de la table — une sauvegarde, un export —
 * ne livre plus le second facteur des comptes. Et les secrets enregistrés en clair avant se
 * migrent, une seule fois.
 */
final class TwoFactorEncryptionTest extends AbstractFunctionalTestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    public function testTheSecretIsStoredEncryptedAndReadBackInClear(): void
    {
        $entityManager = $this->entityManager();
        $this->persist($entityManager, 'ada@example.test', self::SECRET);

        $stored = $entityManager->getConnection()->fetchOne('SELECT totp_secret FROM account');
        self::assertIsString($stored);
        self::assertStringNotContainsString(self::SECRET, $stored, 'La table ne garde plus le secret lisible.');

        self::assertSame(self::SECRET, $this->secretOf($entityManager, 'ada@example.test'), 'Le compte relit son secret, et ses codes restent bons.');
    }

    public function testTheMigrationEncryptsTheClearSecretsOnceAndOnlyThem(): void
    {
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();
        $this->persist($entityManager, 'old@example.test', self::SECRET);
        $this->persist($entityManager, 'new@example.test', 'KRSXG5CTMVRXEZLUKRSXG5CTMVRXEZLU');
        // Un compte enrôlé avant 1.39 : son secret est en clair dans la table.
        $connection->executeStatement('UPDATE account SET totp_secret = ? WHERE email = ?', [self::SECRET, 'old@example.test']);
        $alreadyEncrypted = $connection->fetchOne('SELECT totp_secret FROM account WHERE email = ?', ['new@example.test']);

        self::assertSame(1, TotpSecretEncryption::encryptStoredSecrets($connection, 'account'));

        $migrated = $connection->fetchOne('SELECT totp_secret FROM account WHERE email = ?', ['old@example.test']);
        self::assertIsString($migrated);
        self::assertStringNotContainsString(self::SECRET, $migrated);
        self::assertSame($alreadyEncrypted, $connection->fetchOne('SELECT totp_secret FROM account WHERE email = ?', ['new@example.test']), 'Un secret déjà chiffré n\'est pas rechiffré.');
        self::assertSame(self::SECRET, $this->secretOf($entityManager, 'old@example.test'), 'Le compte enrôlé avant la migration garde son second facteur.');

        self::assertSame(0, TotpSecretEncryption::encryptStoredSecrets($connection, 'account'), 'Rejouée, la migration ne chiffre rien.');
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->boot(bundleConfig: ['two_factor' => ['enabled' => true]], withOrm: true, withTwoFactor: true)
            ->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        // Ce que core-bundle fait à chaque requête et à chaque commande : confier le chiffreur au type.
        EncryptedStringType::setEncryptor(new Encryptor(TestKernel::ENCRYPTION_KEY));
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }

    private function persist(EntityManagerInterface $entityManager, string $email, string $secret): void
    {
        $account = new Account()->setEmail($email)->setFullName('Ada Lovelace');
        $account->startTotpEnrolment($secret)->confirmTotp(new \DateTimeImmutable());
        $entityManager->persist($account);
        $entityManager->flush();
        $entityManager->clear();
    }

    private function secretOf(EntityManagerInterface $entityManager, string $email): ?string
    {
        $entityManager->clear();

        return $entityManager->getRepository(Account::class)->findOneBy(['email' => $email])?->getTotpAuthenticationConfiguration()?->getSecret();
    }
}
