<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\UserImport;

/**
 * An address the way an account stores it: core-bundle's normalisation when it is there, its equivalent otherwise.
 *
 * @internal
 */
final class UserEmail
{
    private const string CORE = 'Jul6Art\CoreBundle\Util\Strings';

    public static function normalize(?string $email): ?string
    {
        if (null === $email) {
            return null;
        }

        if (class_exists(self::CORE) && method_exists(self::CORE, 'lowerEmail')) {
            $normalized = \call_user_func([self::CORE, 'lowerEmail'], $email);

            return \is_string($normalized) && '' !== $normalized ? $normalized : null;
        }

        $normalized = mb_strtolower(trim($email));

        return '' === $normalized ? null : $normalized;
    }
}
