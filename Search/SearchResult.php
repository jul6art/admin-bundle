<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Search;

/**
 * One line of the global search panel: what is read, and where it leads.
 *
 * ⚠️ **Two scalars, never an entity.** The panel shows a label and follows a link; carrying the
 * object would make the template the one place on the screen able to trigger a query — once per
 * line.
 */
final readonly class SearchResult
{
    public function __construct(
        public string $label,
        public string $url,
    ) {
    }
}
