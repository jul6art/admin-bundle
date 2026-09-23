<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Search;

/**
 * One family of the header search — "customers", "items" — as a product declares it.
 *
 * Declarative on purpose: the engine builds the query, so a product cannot get the comparison, the
 * limit or the count wrong family by family. What a family CANNOT say is who may search it: that is
 * the source's `families()`, which returns only what the current actor may open.
 */
final readonly class SearchFamily
{
    /**
     * @param string       $key        the group's key in the JSON — and the one the partial's `labels` translate
     * @param class-string $entityClass
     * @param list<string> $fields     searched with OR, on the root alias `e` (`address.city` works: embeddables are paths)
     * @param string       $showRoute  where a result leads, called with `['id' => …]`
     * @param string       $label      a DQL expression over `e` — `e.name`, or `CONCAT(e.firstName, ' ', e.lastName)`
     * @param string|null  $listRoute  where "see all" leads, called with `['search' => term]` — the list must
     *                                 search the SAME fields, or the two counts disagree
     */
    public function __construct(
        public string $key,
        public string $entityClass,
        public array $fields,
        public string $showRoute,
        public string $label = 'e.name',
        public ?string $listRoute = null,
    ) {
    }
}
