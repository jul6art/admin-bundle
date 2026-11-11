<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\TwoFactor;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;

/**
 * Chiffre les secrets TOTP stockés EN CLAIR avant admin-bundle 1.39, depuis une migration.
 *
 * ```php
 * public function postUp(Schema $schema): void
 * {
 *     TotpSecretEncryption::encryptStoredSecrets($this->connection);
 * }
 * ```
 *
 * ⚠️ **Rejouable.** Une valeur que le type `encrypted_string` déchiffre est laissée telle quelle :
 * seul ce qui échoue au déchiffrement — un secret base32 en clair — est réécrit. Une migration
 * rejouée, ou jouée sur une base déjà migrée à la main, ne chiffre donc rien deux fois.
 *
 * ⚠️ **Dans `postUp()`, pas dans `up()`** : `up()` ne fait que mettre des requêtes en file, la
 * colonne n'est élargie (en `TEXT`) qu'après ; et le type a besoin de son chiffreur, que
 * core-bundle confie au type à chaque commande console — `doctrine:migrations:migrate` comprise.
 */
final class TotpSecretEncryption
{
    /**
     * @return int le nombre de secrets chiffrés
     */
    public static function encryptStoredSecrets(Connection $connection, string $table = 'user', string $column = 'totp_secret', string $identifier = 'id'): int
    {
        $type = Type::getType('encrypted_string');
        $platform = $connection->getDatabasePlatform();
        $quotedTable = $connection->quoteSingleIdentifier($table);
        $quotedColumn = $connection->quoteSingleIdentifier($column);
        $quotedIdentifier = $connection->quoteSingleIdentifier($identifier);

        $rows = $connection->fetchAllAssociative(\sprintf(
            'SELECT %s AS id, %s AS secret FROM %s WHERE %s IS NOT NULL',
            $quotedIdentifier,
            $quotedColumn,
            $quotedTable,
            $quotedColumn,
        ));

        $encrypted = 0;
        foreach ($rows as $row) {
            $secret = $row['secret'];
            if (!\is_string($secret) || '' === $secret) {
                continue;
            }

            try {
                $type->convertToPHPValue($secret, $platform);

                continue;
            } catch (\Exception) {
                // Pas déchiffrable : un secret d'avant 1.39, en clair. `\Exception` et non l'exception
                // que l'interface des types déclare : le chiffreur de core-bundle lève une
                // `RuntimeException` (charge malformée ou altérée). Une `LogicException` — le type sans
                // chiffreur — ressortira de toute façon à la ligne suivante.
            }

            $connection->executeStatement(
                \sprintf('UPDATE %s SET %s = ? WHERE %s = ?', $quotedTable, $quotedColumn, $quotedIdentifier),
                [$type->convertToDatabaseValue($secret, $platform), $row['id']],
            );
            ++$encrypted;
        }

        return $encrypted;
    }
}
