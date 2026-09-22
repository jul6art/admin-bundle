<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Search;

/**
 * A family of results for the header's global search, and what it REALLY counts.
 *
 * ⚠️ `$total` is not `count($results)`: the panel shows a few lines and must be able to say "5 of
 * 47". Without the total the user believes they have seen everything, and the search lies by
 * omission about the one fact that would send them to the family's own screen.
 *
 * Serialised as is (`{ results: [{label, url}], total }`), keyed by family, it is the payload
 * `global-search_controller.js` renders.
 */
final readonly class SearchGroup
{
    /** @param list<SearchResult> $results */
    public function __construct(
        public array $results,
        public int $total,
    ) {
    }
}
