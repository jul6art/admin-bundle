<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\UserImport;

use Jul6Art\DataflowBundle\Import\RowMapperInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * One line of an accounts file → a new account of `admin.user_import.user_class` (ADR-0001 of dataflow-bundle).
 *
 * - ⚠️ **A random password**: the account is opened by "forgotten password". A file of passwords would be a file of
 *   secrets sent by e-mail, then kept.
 * - ⚠️ **A role from the configured list only**, and the super administrator's role only from a super administrator.
 *   An administrator holds `user:create` by default: importing a super administrator at an address of their own,
 *   then opening it by "forgotten password", was an elevation every copy but one still allowed (2026-11-13).
 *

 */
final readonly class UserRowMapper implements RowMapperInterface
{
    /**
     * The columns a file can feed, in the order the mapping screen offers them.
     *
     * @var list<string>
     */
    public const array FIELDS = ['firstName', 'lastName', 'email', 'role'];

    /**
     * @param class-string $userClass
     * @param list<string> $roles     the roles a line may give; empty: the account keeps the implicit one only
     */
    public function __construct(
        private string $userClass,
        private array $roles,
        private string $superAdminRole,
        private UserPasswordHasherInterface $hasher,
        private AuthorizationCheckerInterface $authorization,
    ) {
    }

    #[\Override]
    public function fields(): array
    {
        return self::FIELDS;
    }

    #[\Override]
    public function map(array $row, ?object $existing = null): object
    {
        // ⚠️ The three are required: an account without a name is identifiable in no list, and one without an address
        // can neither sign in nor reset its password — created, invisible, unreachable.
        $firstName = trim($row['firstName'] ?? '');
        $lastName = trim($row['lastName'] ?? '');

        if ('' === $firstName || '' === $lastName || !isset($row['email'])) {
            throw new \DomainException('user.import.error.missing_fields');
        }

        // ⚠️ The same normalisation as the entity's, so the duplicate key and the UNIQUE column always agree.
        $email = UserEmail::normalize($row['email']);

        if (null === $email || !str_contains($email, '@')) {
            throw new \DomainException('user.import.error.invalid_email');
        }

        $role = trim($row['role'] ?? '');

        if ('' !== $role && !\in_array($role, $this->roles, true)) {
            throw new \DomainException('user.import.error.unknown_role');
        }

        if ($role === $this->superAdminRole && !$this->authorization->isGranted($this->superAdminRole)) {
            throw new \DomainException('user.import.error.role_forbidden');
        }

        $user = new $this->userClass();

        foreach (['setFirstName' => $firstName, 'setLastName' => $lastName, 'setEmail' => $email, 'setRoles' => '' === $role ? [] : [$role], 'setIsActive' => true] as $setter => $value) {
            if (method_exists($user, $setter)) {
                $user->{$setter}($value);
            }
        }

        if (!$user instanceof PasswordAuthenticatedUserInterface || !method_exists($user, 'setPassword')) {
            throw new \LogicException(\sprintf('%s cannot hold a password: an imported account would have none to reset.', $this->userClass));
        }

        $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));

        return $user;
    }
}
