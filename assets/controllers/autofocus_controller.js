import { Controller } from '@hotwired/stimulus';

/**
 * Puts the cursor in the first usable field of a form, on connect.
 *
 * ```twig
 * {{ form_start(form, { attr: { 'data-controller': 'form--autofocus form--submit-shortcut' } }) }}
 * ```
 *
 * ⚠️ **A controller rather than the native `autofocus` attribute.** `autofocus` fires once, on the
 * initial document load; Stimulus re-runs `connect()` after a Turbo navigation or a Mercure-driven
 * DOM swap. On a back-office where forms arrive without a full page load, the native attribute
 * works exactly on the first form of a session and on no other.
 *
 * ⚠️ **Mount it on entry forms only** — `new` and `edit`. A filter bar or a search box that stole
 * the focus would move the page under a reader who just arrived, and a screen-reader user would
 * lose their place.
 *
 * Skip one field with `data-autofocus-skip`. When nothing qualifies the controller does nothing —
 * it never throws.
 */
export default class extends Controller {
    connect() {
        // ⚠️ Deferred by one microtask on purpose: a sibling controller — Select2 above all — mounts
        // a focus proxy over its own field during the same tick. Focusing before it has done so
        // puts the cursor in an element that is about to be hidden, and the form opens with no
        // visible caret at all.
        queueMicrotask(() => this._focusFirst());
    }

    _focusFirst() {
        if (!(this.element instanceof HTMLElement)) {
            return;
        }

        const candidates = this.element.querySelectorAll([
            'input:not([type=hidden]):not([readonly]):not([disabled]):not([data-autofocus-skip])',
            'textarea:not([readonly]):not([disabled]):not([data-autofocus-skip])',
            'select:not([disabled]):not([data-autofocus-skip])',
            '[contenteditable=true]:not([data-autofocus-skip])',
        ].join(','));

        for (const element of candidates) {
            if (!this._isVisible(element)) {
                continue;
            }

            try {
                element.focus({ preventScroll: false });
            } catch (_) {
                // Some widgets refuse focus outright. Silent skip: an exception here would break
                // the whole form over a cosmetic convenience.
            }

            return;
        }
    }

    _isVisible(element) {
        if (!(element instanceof HTMLElement) || element.hidden) {
            return false;
        }

        return element.offsetParent !== null || getComputedStyle(element).position === 'fixed';
    }
}
