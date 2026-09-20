import { Controller } from '@hotwired/stimulus';
import { translatableValues, useTranslatable } from '@jul6art/core-bundle/mixins/translatable';

/**
 * Turns a text input into a "press the combination you want" widget, for a settings screen.
 *
 * ```php
 * ->add('form_save', TextType::class, [
 *     'attr' => [
 *         'data-controller' => 'form--shortcut-capture',
 *         'data-form--shortcut-capture-default-value' => 'ctrl+enter',
 *     ],
 * ])
 * ```
 *
 * The input is made read-only and never typed into: the user presses the combination, and the
 * field receives its canonical form (`ctrl+shift+l`) — the same string
 * {@see \Jul6Art\AdminBundle\Keyboard\KeyboardComboNormalizer} validates on the server.
 *
 * ⚠️ **The grammar below is the third copy of the same rules** (the normalizer, the router, this).
 * They must agree: a combination this widget accepts and the router cannot build is a preference
 * the user saved and that never fires, with nothing on screen to explain it.
 *
 * ⚠️ **`preventDefault()` on the function keys is deliberate.** F1 opens help, F11 goes fullscreen,
 * F12 opens the developer tools — while this field has focus, the keystroke belongs to it. It is
 * also the only way those keys can be offered at all, and they are the ones least likely to clash
 * with anything else.
 *
 * ⚠️ **Labels come from the catalogue, never from a fallback in this file.** An English default
 * hard-coded here would show up untranslated on exactly the screens where a user is configuring
 * their own language.
 */
const KEY_PATTERN = /^([a-z0-9]|enter|space|f([1-9]|1[0-2]))$/;

const PRETTY = { ctrl: 'Ctrl', shift: 'Shift', alt: 'Alt', enter: 'Enter', space: 'Space' };

function prettify(combo) {
    return String(combo || '')
        .split('+')
        .map((part) => PRETTY[part] || part.toUpperCase())
        .join(' + ');
}

function comboFromEvent(event) {
    const raw = event.key || '';
    const lower = raw.toLowerCase();
    const token = raw === ' ' ? 'space' : lower;

    if (!KEY_PATTERN.test(token)) {
        return null;
    }

    const parts = [];

    if (event.ctrlKey) parts.push('ctrl');
    if (event.shiftKey) parts.push('shift');
    if (event.altKey) parts.push('alt');

    parts.push(token);

    return parts.join('+');
}

export default class extends Controller {
    static values = {
        ...translatableValues,
        default: { type: String, default: '' },
    };

    connect() {
        useTranslatable(this);

        this.element.readOnly = true;
        this.element.classList.add('cursor-pointer');

        this._onFocus = this._onFocus.bind(this);
        this._onBlur = this._onBlur.bind(this);
        this._onKeydown = this._onKeydown.bind(this);

        this._renderBadge();

        this.element.addEventListener('focus', this._onFocus);
        this.element.addEventListener('blur', this._onBlur);
        this.element.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        this.element.removeEventListener('focus', this._onFocus);
        this.element.removeEventListener('blur', this._onBlur);
        this.element.removeEventListener('keydown', this._onKeydown);
        this._badge?.remove();
        this._badge = null;
    }

    _renderBadge() {
        const badge = document.createElement('div');
        badge.className = 'mt-2 flex items-center gap-3 text-xs text-slate-500 dark:text-slate-400';
        badge.innerHTML = '<span data-current></span><button type="button" data-reset hidden class="underline hover:text-accent-600 dark:hover:text-accent-400"></button>';

        this.element.parentNode?.appendChild(badge);
        this._badge = badge;

        badge.querySelector('[data-reset]').addEventListener('click', (event) => {
            event.preventDefault();
            this.element.value = '';
            this._sync();
        });

        this._sync();
    }

    _onFocus() {
        this._say(this.t('keyboard.capture.press'));
    }

    _onBlur() {
        this._sync();
    }

    _onKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.element.value = '';
            this.element.blur();

            return;
        }

        // A modifier on its own is not a combination yet — wait for the key it modifies.
        if (['Control', 'Shift', 'Alt', 'Meta'].includes(event.key)) {
            return;
        }

        event.preventDefault();

        if (event.metaKey) {
            this._say(this.t('keyboard.capture.meta_refused'));

            return;
        }

        const combo = comboFromEvent(event);

        if (!combo) {
            this._say(this.t('keyboard.capture.unsupported'));

            return;
        }

        this.element.value = combo;
        this._sync();
    }

    _sync() {
        const value = this.element.value.trim();
        const reset = this._badge?.querySelector('[data-reset]');

        if (value === '') {
            this._say(this.defaultValue ? this.t('keyboard.capture.default', { default: prettify(this.defaultValue) }) : '');

            if (reset) reset.hidden = true;

            return;
        }

        this._say(this.t('keyboard.capture.captured', { combo: prettify(value) }));

        if (reset) {
            reset.hidden = value === this.defaultValue;
            reset.textContent = this.t('keyboard.capture.reset');
        }
    }

    _say(text) {
        const slot = this._badge?.querySelector('[data-current]');

        if (slot) {
            slot.textContent = text;
        }
    }
}
