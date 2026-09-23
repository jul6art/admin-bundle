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
    /**
     * @param list<SearchResult> $results
     * @param string|null        $url     where "see all" leads — the family's own list, filtered by
     *                                    the same term; the panel links its "N results" hint to it
     *                                    when the total exceeds what is shown (since 1.16)
     */
    public function __construct(
        public array $results,
        public int $total,
        public ?string $url = null,
    ) {
    }
}
