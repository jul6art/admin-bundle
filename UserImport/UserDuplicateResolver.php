<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\UserImport;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Jul6Art\DataflowBundle\Import\DuplicateResolverInterface;

/**
 * An account is a duplicate of the stored one with the same address — normalised as the entity normalises it.
 *
 * ⚠️ **One query for the batch**, never one per row: a file of five thousand lines would otherwise cost five thousand
 * round-trips before the first write.
 */
final readonly class UserDuplicateResolver implements DuplicateResolverInterface
{
    /**
     * @param class-string $userClass
     */
    public function __construct(
        private string $userClass,
        private ManagerRegistry $registry,
    ) {
    }

    #[\Override]
    public function keyOf(array $row): ?string
    {
        return UserEmail::normalize($row['email'] ?? null);
    }

    #[\Override]
    public function findExisting(array $rows): array
    {
        $emails = [];

        foreach ($rows as $index => $row) {
            $key = $this->keyOf($row);

            if (null !== $key) {
                $emails[$index] = $key;
            }
        }

        if ([] === $emails) {
            return [];
        }

        $manager = $this->registry->getManagerForClass($this->userClass);

        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(\sprintf('No Doctrine ORM entity manager maps "%s" (admin.user_import.user_class).', $this->userClass));
        }

        /** @var list<object> $stored */
        $stored = $manager->createQueryBuilder()
            ->select('u')
            ->from($this->userClass, 'u')
            ->where('u.email IN (:emails)')
            ->setParameter('emails', array_values(array_unique($emails)))
            ->getQuery()
            ->getResult();

        $byEmail = [];

        foreach ($stored as $account) {
            if (method_exists($account, 'getEmail')) {
                $byEmail[UserEmail::normalize($account->getEmail())] = $account;
            }
        }

        $existing = [];

        foreach ($emails as $index => $email) {
            if (isset($byEmail[$email])) {
                $existing[$index] = $byEmail[$email];
            }
        }

        return $existing;
    }
}
