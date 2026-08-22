<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Navigation;

/**
 * A collapsible group of links.
 *
 * `key` is what the open/closed state is remembered under, so it has to be stable and unique
 * across the whole application — two sections sharing a key toggle together, which reads as a bug
 * in the sidebar rather than in the menu declaration.
 */
final readonly class NavSection
{
    /**
     * @param string        $key        stable identifier, used for the persisted open state
     * @param list<NavItem> $items
     * @param string|null   $permission gate for the whole section, on top of each item's own
     * @param string|null   $feature    feature gate for the whole section
     * @param int           $priority   higher comes first; equal priorities keep declaration order
     */
    public function __construct(
        public string $key,
        public string $labelKey,
        public string $icon,
        public array $items,
        public string $labelDomain = 'messages',
        public ?string $permission = null,
        public ?string $feature = null,
        public int $priority = 0,
    ) {
    }

    /**
     * @param list<NavItem> $items
     */
    public function withItems(array $items): self
    {
        return new self(
            $this->key,
            $this->labelKey,
            $this->icon,
            $items,
            $this->labelDomain,
            $this->permission,
            $this->feature,
            $this->priority,
        );
    }
}
