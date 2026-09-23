<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures;

use Doctrine\ORM\QueryBuilder;
use Jul6Art\AdminBundle\Search\GlobalSearchSourceInterface;
use Jul6Art\AdminBundle\Search\SearchFamily;
use Jul6Art\AdminBundle\Tests\Fixtures\Entity\Account;

/**
 * A search source over the `Account` fixture, which records how often its families were asked.
 */
final class RecordingSearchSource implements GlobalSearchSourceInterface
{
    public int $asked = 0;

    public function __construct(
        private readonly ?string $listRoute = null,
        private readonly ?string $scope = null,
        private readonly bool $families = true,
    ) {
    }

    #[\Override]
    public function families(): iterable
    {
        ++$this->asked;

        return $this->families
            ? [new SearchFamily('accounts', Account::class, ['email', 'fullName'], 'admin_widget_show', label: 'e.fullName', listRoute: $this->listRoute)]
            : [];
    }

    #[\Override]
    public function scope(SearchFamily $family, QueryBuilder $builder): void
    {
        if (null !== $this->scope) {
            $builder->andWhere($this->scope);
        }
    }
}
