<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Acl\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AclBundle\Contract\AclTenantInterface;
use Jul6Art\AclBundle\Contract\AclUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** An application's account, as the engine and the security token see it. */
#[ORM\Entity]
#[ORM\Table(name: 'admin_test_member')]
class Member implements AclUserInterface, UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * @param list<string> $roles
     */
    public function __construct(
        #[ORM\Column(length: 64)]
        private string $email,
        #[ORM\Column(type: Types::JSON)]
        private array $roles = [],
        #[ORM\ManyToOne(targetEntity: Organization::class)]
        #[ORM\JoinColumn(nullable: true)]
        private ?Organization $organization = null,
    ) {
    }

    #[\Override]
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return $this->roles;
    }

    #[\Override]
    public function isActive(): bool
    {
        return true;
    }

    #[\Override]
    public function isSuperAdmin(): bool
    {
        return \in_array('ROLE_SUPER_ADMIN', $this->roles, true);
    }

    #[\Override]
    public function getTenant(): ?AclTenantInterface
    {
        return $this->organization;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : 'member';
    }

    public function eraseCredentials(): void
    {
    }
}
