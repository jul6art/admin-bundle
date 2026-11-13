<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Doctrine\AbstractUserPermissionOverride;

/** The thin override of the tests' application. */
#[ORM\Entity]
#[ORM\Table(name: 'admin_test_user_permission_override')]
class UserPermissionOverride extends AbstractUserPermissionOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
