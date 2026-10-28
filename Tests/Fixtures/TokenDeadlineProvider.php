<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures;

use Jul6Art\AdminBundle\Deadline\DeadlineProviderInterface;
use Jul6Art\AdminBundle\Deadline\ProvidedDeadline;

/**
 * A provider declared as an application would — a service, no tag: `AdminBundle` autoconfigures it.
 * What it yields is set by the test.
 */
final class TokenDeadlineProvider implements DeadlineProviderInterface
{
    /** @var list<ProvidedDeadline> */
    public array $deadlines = [];

    #[\Override]
    public function deadlines(): iterable
    {
        return $this->deadlines;
    }
}
