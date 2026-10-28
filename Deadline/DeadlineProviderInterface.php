<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Deadline;

/**
 * The deadlines the application already knows — a token's date of entry plus its known lifetime:
 * written into the table before each reminder run, shown on the screen without being editable.
 *
 * Declaring the class as a service is enough: `AdminBundle` autoconfigures the tag. A source no
 * provider yields any more is removed — no token, no deadline.
 */
interface DeadlineProviderInterface
{
    /**
     * @return iterable<ProvidedDeadline>
     */
    public function deadlines(): iterable;
}
