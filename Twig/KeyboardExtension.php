<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Twig;

use Jul6Art\AdminBundle\Keyboard\KeyboardAction;
use Jul6Art\AdminBundle\Keyboard\KeyboardShortcutResolver;
use Twig\Attribute\AsTwigFunction;

/**
 * What a template needs to write a shortcut on an element without knowing who is looking at it.
 *
 * ```twig
 * <a href="{{ path('admin_customer_new') }}"
 *    data-shortcut="{{ keyboard_shortcut('global.new') }}"
 *    data-shortcut-label="{{ 'customer.list.create'|trans }}">
 * ```
 *
 * ⚠️ **`data-shortcut-label` is what puts the entry in the cheat-sheet.** An element with a combo
 * and no label still fires, and `?` does not list it — the shortcut exists and nothing says so.
 *
 * ⚠️ **The template writes the shortcut on the element that ALREADY carries the permission
 * check.** A button rendered under `is_granted(...)` is absent for an account that may not press
 * it, so the shortcut is absent too: the router clicks a node, it does not know about roles. That
 * is the whole reason this is an attribute rather than a registry.
 */
final readonly class KeyboardExtension
{
    public function __construct(
        private KeyboardShortcutResolver $resolver,
    ) {
    }

    /** The combo in force for an action, or `''` for an unknown code. */
    #[AsTwigFunction(name: 'keyboard_shortcut')]
    public function shortcut(string $code): string
    {
        return $this->resolver->resolve($code);
    }

    /**
     * Every action and its combo — for the layout's meta tags and for a settings screen.
     *
     * @return array<string, string>
     */
    #[AsTwigFunction(name: 'keyboard_shortcuts')]
    public function shortcuts(): array
    {
        return $this->resolver->snapshot();
    }

    /**
     * The catalogue itself, for a screen that lists the actions with their labels.
     *
     * @return list<KeyboardAction>
     */
    #[AsTwigFunction(name: 'keyboard_actions')]
    public function actions(): array
    {
        return $this->resolver->actions();
    }
}
