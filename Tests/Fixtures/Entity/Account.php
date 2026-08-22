<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\AdminBundle\Appearance\ColorMode;
use Jul6Art\AdminBundle\Contract\AdminUserInterface;
use Jul6Art\AdminBundle\Contract\AppearanceAwareInterface;
use Jul6Art\AdminBundle\Entity\Traits\AppearancePreferencesTrait;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What a consuming application writes: its own `User`, using the trait and implementing the two
 * optional contracts.
 *
 * The colour mode is stored under `theme` on purpose — that is the column name every application
 * of this ecosystem already had before the bundle existed, and the point of leaving
 * `getColorMode()` out of the trait is precisely that it can sit on whatever column is already
 * there.
 */
#[ORM\Entity]
#[ORM\Table(name: 'account')]
class Account implements AdminUserInterface, AppearanceAwareInterface, UserInterface
{
    use AppearancePreferencesTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    #[ORM\Column(length: 100)]
    private string $fullName = '';

    #[ORM\Column(length: 10, options: ['default' => 'light'])]
    private string $theme = 'light';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

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
        $this->email = $email;

        return $this;
    }

    public function setFullName(string $fullName): static
    {
        $this->fullName = $fullName;

        return $this;
    }

    #[\Override]
    public function getColorMode(): ColorMode
    {
        return ColorMode::fromStorage($this->theme);
    }

    #[\Override]
    public function setColorMode(ColorMode $mode): static
    {
        $this->theme = $mode->value;

        return $this;
    }

    /** The raw column, so a test can prove the mode round-trips through storage. */
    public function getTheme(): string
    {
        return $this->theme;
    }

    #[\Override]
    public function getDisplayName(): string
    {
        return $this->fullName;
    }

    #[\Override]
    public function getInitials(): string
    {
        $parts = preg_split('/\s+/', trim($this->fullName)) ?: [];

        return mb_strtoupper(implode('', array_map(static fn (string $p): string => mb_substr($p, 0, 1), \array_slice($parts, 0, 2))));
    }

    #[\Override]
    public function getAvatarPath(): ?string
    {
        return $this->avatarPath;
    }

    public function setAvatarPath(?string $avatarPath): static
    {
        $this->avatarPath = $avatarPath;

        return $this;
    }

    #[\Override]
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    #[\Override]
    public function getUserIdentifier(): string
    {
        // `UserInterface` promet une chaîne non vide ; la colonne, elle, a une valeur par défaut
        // vide pour qu'un `new Account()` soit constructible dans un test.
        return '' === $this->email ? 'anonymous' : $this->email;
    }
}
