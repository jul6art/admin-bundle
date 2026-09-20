<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\DependencyInjection\Compiler;

use Jul6Art\AdminBundle\Keyboard\KeyboardShortcutResolver;
use Jul6Art\AdminBundle\Keyboard\KeyboardShortcutStoreInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Leaves the shortcut resolver without a store when the application registered none.
 *
 * This is a **service** question, not a class one — the interface always exists, the bundle ships
 * it; what is uncertain is whether anything implements it. An extension cannot tell: it runs before
 * the other bundles have configured anything, so `$container->has()` there always answers false.
 * Hence a pass, exactly as for {@see FeatureVisibilityPass}.
 *
 * ⚠️ **What the absence means here is the opposite of what it means elsewhere in this ecosystem.**
 * Without a datatable preference store, `jul6art/datatable-bundle` REMOVES its controller and the
 * feature silently ceases to exist. Without a keyboard store, every shortcut still works on its
 * factory combo and only CUSTOMISATION is gone. A back-office that stopped answering `Ctrl+Enter`
 * because nobody wired a database would be indistinguishable from a broken one.
 */
final class KeyboardStorePass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(KeyboardShortcutResolver::class)) {
            return;
        }

        if ($container->has(KeyboardShortcutStoreInterface::class)) {
            return;
        }

        $container->getDefinition(KeyboardShortcutResolver::class)->setArgument('$store', null);
    }
}
