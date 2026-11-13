<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Account\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A back-office account as the skeleton writes it: names, an active flag, a picture, a language.
 */
#[ORM\Entity]
#[ORM\Table(name: 'member')]
class Member implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    private ?string $plainPassword = null;

    #[ORM\Column]
    private string $firstName = '';

    #[ORM\Column]
    private string $lastName = '';

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column]
    private string $locale = 'en';

    #[ORM\Column(nullable: true)]
    private ?string $completedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        return '' !== $this->email ? $this->email : 'unknown';
    }

    #[\Override]
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Override]
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;

        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function getCompletedBy(): ?string
    {
        return $this->completedBy;
    }

    public function completeBy(string $listener): static
    {
        $this->completedBy = $listener;

        return $this;
    }

    /**
     * Still abstract on `UserInterface` under Symfony 7.4, gone in 8.0: no `#[\Override]`.
     */
    #[\Deprecated(message: 'since Symfony 7.3, there are no credentials to erase')]
    public function eraseCredentials(): void
    {
    }
}
