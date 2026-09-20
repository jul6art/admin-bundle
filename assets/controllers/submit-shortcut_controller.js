import { Controller } from '@hotwired/stimulus';

/**
 * Submits a form from the keyboard, and keeps a bare `Enter` from doing it by accident.
 *
 * ```twig
 * {{ form_start(form, { attr: { 'data-controller': 'form--submit-shortcut' } }) }}
 * ```
 *
 * | Combo                    | Effect                                                            |
 * |--------------------------|-------------------------------------------------------------------|
 * | `Ctrl`/`⌘` + `Enter`     | the primary submit button                                          |
 * | `Ctrl`/`⌘` + `Shift` + `Enter` | the `data-secondary` button ("save and add another"), else the primary |
 *
 * Both are overridable per visitor: the layout prints `<meta name="kb.form.save">` and
 * `kb.form.save_and_new` with the combos in force, and this controller reads them.
 *
 * ⚠️ **`requestSubmit(button)` and never `form.submit()` nor `button.click()`.** It runs HTML5
 * validation, fires `formdata` listeners, and — the part that matters — carries the button's
 * `name`/`value` into the payload. That is how `_after_save=new` reaches the server with no extra
 * hidden field. A synthetic `click()` was tried and is worse than useless: dispatched while Ctrl
 * and Shift are physically held, the browser re-reads it as a "open in a new tab" click.
 */
const ALLOWED_MODIFIERS = new Set(['ctrl', 'shift', 'alt']);

/** `'ctrl+shift+enter'` → `{key: 'enter', ctrl: true, shift: true, alt: false}`; `null` if malformed. */
function parseCombo(combo) {
    if (typeof combo !== 'string' || combo === '') {
        return null;
    }

    const parts = combo.toLowerCase().split('+').map((part) => part.trim()).filter((part) => part !== '');

    if (parts.length === 0) {
        return null;
    }

    const parsed = { key: parts.pop(), ctrl: false, shift: false, alt: false };

    for (const modifier of parts) {
        if (!ALLOWED_MODIFIERS.has(modifier)) {
            return null;
        }

        parsed[modifier] = true;
    }

    return parsed;
}

/**
 * ⚠️ `Cmd` counts as `Ctrl` HERE and nowhere else. This is the one place where the platform
 * accelerator matters — macOS users type ⌘+Enter to save in every application they own — and the
 * combo vocabulary deliberately has no `meta` token, so the two are folded at the point of use.
 */
function matchesEvent(parsed, event) {
    if (!parsed) {
        return false;
    }

    const key = event.key === ' ' ? 'space' : (event.key || '').toLowerCase();

    return key === parsed.key
        && parsed.ctrl === (event.ctrlKey || event.metaKey)
        && parsed.shift === event.shiftKey
        && parsed.alt === event.altKey;
}

function readOverride(name) {
    return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? null;
}

export default class extends Controller {
    static values = {
        saveCombo: { type: String, default: 'ctrl+enter' },
        saveAndNewCombo: { type: String, default: 'ctrl+shift+enter' },
    };

    connect() {
        this._save = parseCombo(readOverride('kb.form.save') ?? this.saveComboValue) ?? parseCombo('ctrl+enter');
        this._saveAndNew = parseCombo(readOverride('kb.form.save_and_new') ?? this.saveAndNewComboValue)
            ?? parseCombo('ctrl+shift+enter');

        this._onKeydown = this._onKeydown.bind(this);
        this._onEnterGuard = this._onEnterGuard.bind(this);

        this.element.addEventListener('keydown', this._onEnterGuard);

        // ⚠️ **CAPTURE phase, on the document — not bubble phase on the form.** Capture beats any
        // inner handler (a rich editor, Select2) that would `stopPropagation()` before the event
        // ever reached the form. And the document level puts us ahead of the browser's own
        // hard-wired "Ctrl+Shift+Enter inside a form = submit in a new tab".
        document.addEventListener('keydown', this._onKeydown, { capture: true });
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown, { capture: true });
        this.element.removeEventListener('keydown', this._onEnterGuard);
    }

    /**
     * A bare `Enter` on a `<select>`, a checkbox or a radio must not submit the document.
     *
     * ⚠️ On a checkbox or radio it also TOGGLES the control, because natively `Enter` never checks a
     * box — only `Space` does. Merely blocking the submit left the box feeling dead under keyboard
     * navigation, which is the opposite of what this whole feature is for.
     *
     * ⚠️ **Buttons are spared on purpose.** A focused button's native `Enter` action is to activate
     * itself; preventing it here would make every keyboard user unable to press any button.
     */
    _onEnterGuard(event) {
        if (event.key !== 'Enter' || event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        const target = event.target;

        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.tagName === 'INPUT' && ['checkbox', 'radio'].includes(target.type)) {
            event.preventDefault();
            target.click();

            return;
        }

        if (target.tagName === 'SELECT') {
            event.preventDefault();
        }
    }

    _onKeydown(event) {
        // Only OUR form. Without this, every form on the page races for the same keystroke.
        if (!(event.target instanceof Node) || !this.element.contains(event.target)) {
            return;
        }

        const wantSecondary = matchesEvent(this._saveAndNew, event);
        const wantPrimary = !wantSecondary && matchesEvent(this._save, event);

        if (!wantSecondary && !wantPrimary) {
            return;
        }

        // ⚠️ Before any branching: Chrome reads Ctrl+Enter in a form field as "submit in a new tab"
        // by simulating a modified click on the implicit submit button.
        event.preventDefault();
        event.stopImmediatePropagation();

        const target = wantSecondary
            ? (this._findSecondarySubmit() ?? this._findPrimarySubmit())
            : this._findPrimarySubmit();

        if (target && typeof this.element.requestSubmit === 'function') {
            this.element.requestSubmit(target);
        }
    }

    _findPrimarySubmit() {
        const buttons = this.element.querySelectorAll('button[type=submit]:not([disabled])');

        for (const button of buttons) {
            if (!button.hasAttribute('data-secondary')) {
                return button;
            }
        }

        return buttons[0] ?? null;
    }

    _findSecondarySubmit() {
        return this.element.querySelector('button[type=submit][data-secondary]:not([disabled])');
    }
}
