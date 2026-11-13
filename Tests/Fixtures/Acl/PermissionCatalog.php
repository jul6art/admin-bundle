<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Acl;

use Jul6Art\AclBundle\Contract\PermissionCatalogInterface;

/** The tests' product: the grid's own permissions, the accounts', and one code for the super admin alone. */
final class PermissionCatalog implements PermissionCatalogInterface
{
    public const array DEFAULTS = [
        'ROLE_ADMIN' => ['permission:read', 'permission:update', 'user:read', 'user:update'],
        'ROLE_USER' => ['user:read'],
    ];

    #[\Override]
    public function all(): array
    {
        return ['permission:read', 'permission:update', 'user:read', 'user:update', 'invoice:cancel'];
    }

    #[\Override]
    public function grouped(): array
    {
        return ['permission' => ['permission:read', 'permission:update'], 'user' => ['user:read', 'user:update'], 'invoice' => ['invoice:cancel']];
    }

    #[\Override]
    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    #[\Override]
    public function superAdminOnly(): array
    {
        return ['invoice:cancel'];
    }
}
