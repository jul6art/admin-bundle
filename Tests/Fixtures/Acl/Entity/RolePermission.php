<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Doctrine\AbstractRolePermission;

/** The thin entity of an application whose grid varies by tenant. */
#[ORM\Entity]
#[ORM\Table(name: 'admin_test_role_permission')]
#[ORM\UniqueConstraint(name: 'uniq_admin_test_role_permission', columns: ['role_code', 'permission', 'organization_id'])]
class RolePermission extends AbstractRolePermission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Organization $organization = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }
}
