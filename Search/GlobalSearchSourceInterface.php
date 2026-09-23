<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Search;

use Doctrine\ORM\QueryBuilder;

/**
 * What a product tells the search engine: which families, and how to narrow them.
 *
 * ⚠️ **Access is decided HERE, not by the engine.** `families()` returns only the families the
 * current actor may open — a family it is not granted is not queried, so no empty group says it
 * exists. The engine knows nothing about roles, permissions, features or tenants.
 *
 * ⚠️ **So is the tenant.** A Doctrine tenant filter is not always on — a platform account often has
 * none — and a search that relied on it would read every tenant for exactly the account nobody
 * tests. `scope()` is where a product writes its tenant or ownership clause, explicitly.
 */
interface GlobalSearchSourceInterface
{
    /**
     * @return iterable<SearchFamily> only the families the current actor may search; empty means no search
     */
    public function families(): iterable;

    /**
     * Narrows one family's query — tenant, ownership. The root alias is `e`.
     */
    public function scope(SearchFamily $family, QueryBuilder $builder): void;
}
