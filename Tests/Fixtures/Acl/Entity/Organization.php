<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Contract\AclTenantInterface;

/** A tenant, as an application maps one: the scope of a grid by tenant. */
#[ORM\Entity]
#[ORM\Table(name: 'admin_test_organization')]
class Organization implements AclTenantInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\Column(length: 64)]
        private string $slug,
    ) {
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[\Override]
    public function getSlug(): string
    {
        return $this->slug;
    }
}
