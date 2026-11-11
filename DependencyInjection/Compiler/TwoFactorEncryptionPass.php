<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection\Compiler;

use Jul6Art\CoreBundle\Security\Encryptor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Le secret TOTP est stocké CHIFFRÉ (1.39) : le second facteur refuse de démarrer sans de quoi le
 * chiffrer.
 *
 * `TwoFactorTrait` mappe `totp_secret` sur le type `encrypted_string` de `jul6art/core-bundle`,
 * qui chiffre avec la clé `core.encryption_key`. Sans le type déclaré, Doctrine échoue au premier
 * chargement d'un compte (« Unknown column type ») ; sans la clé, le type n'a pas de chiffreur et
 * échoue à la première lecture d'un secret. Les deux pannes arrivent en production, à la connexion,
 * loin de leur cause : ce pass les ramène au démarrage, avec la ligne à écrire.
 *
 * ⚠️ Un pass, pas l'extension : la question porte sur ce que DEUX AUTRES bundles ont configuré
 * (core pour le chiffreur, Doctrine pour le type), et une extension s'exécute avant eux.
 */
final class TwoFactorEncryptionPass implements CompilerPassInterface
{
    /** `::class` loads nothing: this bundle does not require core-bundle. */
    private const string ENCRYPTOR = Encryptor::class;

    private const string TYPE = 'encrypted_string';

    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        // Posé par l'extension seulement quand `admin.two_factor.enabled` : sinon, rien à vérifier.
        if (!$container->hasParameter('admin.two_factor.backup_codes')) {
            return;
        }

        if (!$container->has(self::ENCRYPTOR)) {
            throw new \LogicException('admin.two_factor.enabled stores the TOTP secret encrypted (admin-bundle 1.39): it needs jul6art/core-bundle with core.encryption_key set (a base64-encoded 32-byte key, `openssl rand -base64 32`).');
        }

        $types = $container->hasParameter('doctrine.dbal.connection_factory.types') ? $container->getParameter('doctrine.dbal.connection_factory.types') : [];
        if (!\is_array($types) || !\array_key_exists(self::TYPE, $types)) {
            throw new \LogicException('admin.two_factor.enabled stores the TOTP secret encrypted (admin-bundle 1.39): declare the type in config/packages/doctrine.yaml, doctrine.dbal.types.encrypted_string: Jul6Art\CoreBundle\Doctrine\Type\EncryptedStringType.');
        }
    }
}
