<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\UserImport;

use Jul6Art\AdminBundle\Tests\Account\Fixtures\Member;
use Jul6Art\AdminBundle\UserImport\UserRowMapper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

/**
 * The super administrator's guard, when the right to give that role is an attribute of the application's own — a
 * "promote" right — rather than the role.
 */
final class UserRowMapperTest extends TestCase
{
    public function testTheSuperAdministratorsRoleNeedsTheConfiguredAttributeWhenThereIsOne(): void
    {
        $line = ['firstName' => 'Margaret', 'lastName' => 'Hamilton', 'email' => 'margaret@example.test', 'role' => 'ROLE_SUPER_ADMIN'];

        $member = $this->mapper(granted: ['USER_PROMOTE'], attribute: 'USER_PROMOTE')->map($line);
        self::assertInstanceOf(Member::class, $member);
        self::assertSame(['ROLE_SUPER_ADMIN', 'ROLE_USER'], $member->getRoles());

        $this->expectExceptionObject(new \DomainException('user.import.error.role_forbidden'));
        $this->mapper(granted: ['ROLE_SUPER_ADMIN'], attribute: 'USER_PROMOTE')->map($line);
    }

    public function testWithoutAnAttributeTheRoleItselfIsWhatTheActorNeeds(): void
    {
        $line = ['firstName' => 'Margaret', 'lastName' => 'Hamilton', 'email' => 'margaret@example.test', 'role' => 'ROLE_SUPER_ADMIN'];

        self::assertInstanceOf(Member::class, $this->mapper(granted: ['ROLE_SUPER_ADMIN'])->map($line));

        $this->expectExceptionObject(new \DomainException('user.import.error.role_forbidden'));
        $this->mapper(granted: ['USER_PROMOTE'])->map($line);
    }

    /**
     * @param list<string> $granted
     */
    private function mapper(array $granted, ?string $attribute = null): UserRowMapper
    {
        $hasher = new class implements UserPasswordHasherInterface {
            #[\Override]
            public function hashPassword(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): string
            {
                return 'hashed';
            }

            #[\Override]
            public function isPasswordValid(PasswordAuthenticatedUserInterface $user, #[\SensitiveParameter] string $plainPassword): bool
            {
                return false;
            }

            #[\Override]
            public function needsRehash(PasswordAuthenticatedUserInterface $user): bool
            {
                return false;
            }
        };
        $authorization = new readonly class($granted) implements AuthorizationCheckerInterface {
            /** @param list<string> $granted */
            public function __construct(private array $granted)
            {
            }

            #[\Override]
            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                return \in_array($attribute, $this->granted, true);
            }
        };

        return new UserRowMapper(Member::class, ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN'], 'ROLE_SUPER_ADMIN', $hasher, $authorization, $attribute);
    }
}
