<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Navigation;

/**
 * Whether a feature is switched on, for the account currently signed in.
 *
 * A feature flag is not a permission: it says what was bought or activated, not what a person may
 * do. Both gates exist and they fail for different reasons, which is why this is a contract of its
 * own rather than a permission code shaped like `cms:enabled`.
 *
 * > ⚠️ **With no implementation registered, an item carrying a `feature` is hidden.** Deliberately:
 * > a feature gate that opens when its checker is missing turns every paid module into a free one.
 * > An application with no feature system simply leaves `feature` null everywhere.
 */
interface FeatureVisibilityInterface
{
    public function isEnabled(string $featureCode): bool;
}
