<?php

declare(strict_types=1);

namespace Jul6Art\AdminBundle\Keyboard;

/**
 * Every action a keyboard shortcut can fire: the three the shell owns, plus whatever the
 * application declares under `admin.keyboard.actions`.
 *
 * ## Why three built-ins and no more
 *
 * ⚠️ The bundle ships exactly the gestures the SHELL performs — open a creation screen, save, save
 * and start another. Everything else belongs to a product: « add a line » means something in an
 * invoicing screen and nothing in a work-order list. A catalogue that tried to guess them would
 * either be empty or wrong, and each entry it got wrong would be a key stolen from the user.
 *
 * ⚠️ **A project extends by CONFIGURATION, not by subclassing.** The settings screen and the
 * cheat-sheet both iterate this list; a second source of actions would leave one of the two
 * incomplete, and the symptom is a shortcut that fires but that nothing documents.
 */
final readonly class KeyboardCatalogue
{
    public const string GLOBAL_NEW = 'global.new';
    public const string FORM_SAVE = 'form.save';
    public const string FORM_SAVE_AND_NEW = 'form.save_and_new';

    /**
     * Back to the list from an entry form: clicks the form's Cancel link (`_form_actions`).
     *
     * ⚠️ It replaced the `Esc` back-jump (decider, 2026-09-23), which only worked after arriving
     * through a shortcut, could not be overridden, and competed with every control that owns `Esc`.
     * A modifier combo so it fires from inside a field, where one is when one wants to leave.
     */
    public const string FORM_BACK = 'form.back';

    /** @var array<string, KeyboardAction> */
    private array $actions;

    /**
     * @param array<string, array{default: string, label: string}> $extra actions declared by the application
     */
    public function __construct(array $extra = [])
    {
        $actions = [];

        foreach ([
            new KeyboardAction(self::GLOBAL_NEW, 'n', 'keyboard.action.global_new'),
            new KeyboardAction(self::FORM_SAVE, 'ctrl+enter', 'keyboard.action.form_save'),
            new KeyboardAction(self::FORM_SAVE_AND_NEW, 'ctrl+shift+enter', 'keyboard.action.form_save_and_new'),
            new KeyboardAction(self::FORM_BACK, 'ctrl+b', 'keyboard.action.form_back'),
        ] as $action) {
            $actions[$action->code] = $action;
        }

        // ⚠️ Les extras viennent APRÈS, donc une application peut redéfinir le défaut d'une action
        // du socle — changer `n` pour `c` par exemple — sans en créer une seconde. Ce qu'elle ne
        // peut pas faire, c'est en retirer une : le routeur les documente dans son antisèche, et
        // une antisèche qui ment est pire qu'absente.
        foreach ($extra as $code => $definition) {
            $actions[$code] = new KeyboardAction($code, $definition['default'], $definition['label']);
        }

        $this->actions = $actions;
    }

    /** @return list<KeyboardAction> */
    public function all(): array
    {
        return \array_values($this->actions);
    }

    /** The factory combo of an action, or `null` when the code is unknown. */
    public function defaultFor(string $code): ?string
    {
        return ($this->actions[$code] ?? null)?->defaultCombo;
    }

    public function has(string $code): bool
    {
        return isset($this->actions[$code]);
    }
}
