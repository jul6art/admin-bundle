<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Unit;

use Jul6Art\AdminBundle\DependencyInjection\Compiler\TwoFactorEncryptionPass;
use Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType;
use Jul6Art\CoreBundle\Security\Encryptor;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Le second facteur chiffre son secret (1.39) : sans clé ni type, il refuse de démarrer — au
 * démarrage, et non à la première connexion d'un compte en production.
 */
final class TwoFactorEncryptionPassTest extends TestCase
{
    #[DoesNotPerformAssertions]
    public function testNothingIsAskedWhileTheSecondFactorIsOff(): void
    {
        new TwoFactorEncryptionPass()->process(new ContainerBuilder());
    }

    public function testWithoutTheKeyTheSecondFactorRefusesToBoot(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('core.encryption_key');

        new TwoFactorEncryptionPass()->process(self::withTheSecondFactor());
    }

    public function testWithoutTheTypeItRefusesToBootToo(): void
    {
        $container = self::withTheSecondFactor();
        $container->register(Encryptor::class, Encryptor::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('doctrine.dbal.types.encrypted_string');

        new TwoFactorEncryptionPass()->process($container);
    }

    #[DoesNotPerformAssertions]
    public function testWithTheKeyAndTheTypeItBoots(): void
    {
        $container = self::withTheSecondFactor();
        $container->register(Encryptor::class, Encryptor::class);
        $container->setParameter('doctrine.dbal.connection_factory.types', ['encrypted_string' => ['class' => EncryptedStringType::class]]);

        new TwoFactorEncryptionPass()->process($container);
    }

    private static function withTheSecondFactor(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // Le paramètre que l'extension ne pose que quand `admin.two_factor.enabled`.
        $container->setParameter('admin.two_factor.backup_codes', false);

        return $container;
    }
}
