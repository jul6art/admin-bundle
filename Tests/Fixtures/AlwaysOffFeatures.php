<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Tests\Fixtures;

use Jul6Art\AdminBundle\Navigation\FeatureVisibilityInterface;

/**
 * A feature checker that answers a fixed map, so a test can say which modules a tenant bought
 * without standing up a feature system.
 */
final readonly class AlwaysOffFeatures implements FeatureVisibilityInterface
{
    /**
     * @param array<string, bool> $enabled
     */
    public function __construct(private array $enabled = [])
    {
    }

    #[\Override]
    public function isEnabled(string $featureCode): bool
    {
        return $this->enabled[$featureCode] ?? false;
    }
}
