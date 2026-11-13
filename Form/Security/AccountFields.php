<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Form\Security;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormTypeInterface;

/**
 * The widgets of the account forms: ui-bundle's when it is there — the field that shows its password, the e-mail
 * field of the shell — Symfony's otherwise. ui-bundle is a `suggest`: a form must not need it to exist.
 *
 * @internal
 */
final class AccountFields
{
    public const string UI_EMAIL = 'Jul6Art\UiBundle\Form\Type\CustomEmailType';

    public const string UI_PASSWORD = 'Jul6Art\UiBundle\Form\Type\CustomPasswordType';

    /** @return class-string<FormTypeInterface<mixed>> */
    public static function email(): string
    {
        return self::installed(self::UI_EMAIL) ?? EmailType::class;
    }

    /** @return class-string<FormTypeInterface<mixed>> */
    public static function password(): string
    {
        return self::installed(self::UI_PASSWORD) ?? PasswordType::class;
    }

    /**
     * @return class-string<FormTypeInterface<mixed>>|null
     */
    private static function installed(string $type): ?string
    {
        return is_a($type, FormTypeInterface::class, true) ? $type : null;
    }

    /**
     * Whether the account class can hold a field: the form only asks what the entity keeps.
     *
     * @param class-string $class
     */
    public static function writable(string $class, string $property): bool
    {
        return method_exists($class, 'set'.ucfirst($property));
    }
}
