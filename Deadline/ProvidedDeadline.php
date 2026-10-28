<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Deadline;

/**
 * A deadline computed by the application ({@see DeadlineProviderInterface}). `source` is its stable
 * key — the row it updates, run after run.
 */
final readonly class ProvidedDeadline
{
    public function __construct(
        public string $source,
        public string $label,
        public \DateTimeImmutable $dueOn,
        public ?string $renewUrl = null,
        public ?string $note = null,
    ) {
    }
}
