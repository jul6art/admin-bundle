import { Controller } from '@hotwired/stimulus';
import { translatableValues, useTranslatable } from '@jul6art/core-bundle/mixins/translatable';

/**
 * The back-office keyboard router: one listener on `<body>`, dispatching to whatever the page
 * declares.
 *
 * ```twig
 * {% block body_attr %} data-controller="core--keyboard"{% endblock %}
 * ```
 *
 * | Key                    | Effect                                                          |
 * |------------------------|-----------------------------------------------------------------|
 * | any declared combo     | clicks the visible `[data-shortcut="<combo>"]`                   |
 * | `?`                    | opens the cheat-sheet                                           |
 * | `Esc`                  | closes it — and nothing else (`Ctrl+B`, `form.back`, goes back) |
 *
 * ## Why it reads the DOM on every keystroke
 *
 * ⚠️ **No registry, no state.** A shortcut is an attribute on an element, so Mercure can replace a
 * button, a modal can inject one, a permission can withhold one — and the router follows without
 * being told. A registry built at connect time would fire shortcuts for buttons that no longer
 * exist and miss the ones that arrived since.
 *
 * ⚠️ **A button hidden by a permission is a shortcut that does not exist.** The router clicks a
 * node; it knows nothing about roles. That is the whole reason this is an attribute — the template
 * that decides whether to render the button has already asked `is_granted()`.
 *
 * ## The inhibition rule, which is what makes it usable
 *
 * ⚠️ While the caret is in a field, a letter is a letter. Only modifier combos pass —
 * otherwise typing "n" in a search box would navigate away mid-word, and the feature would be
 * uninstalled within the hour.
 */
export default class extends Controller {
    static values = {
        ...translatableValues,
    };

    connect() {
        useTranslatable(this);

        this._onKeydown = this._onKeydown.bind(this);
        this._cheatsheet = null;
        this._previouslyFocused = null;

        // The combos in force, printed by the layout when they differ from the factory default.
        this._combos = {
            globalNew: this._readCombo('kb.global.new', 'n'),
            formSave: this._readCombo('kb.form.save', 'ctrl+enter'),
            formSaveAndNew: this._readCombo('kb.form.save_and_new', 'ctrl+shift+enter'),
            formBack: this._readCombo('kb.form.back', 'ctrl+b'),
        };

        document.addEventListener('keydown', this._onKeydown);
    }

    disconnect() {
        document.removeEventListener('keydown', this._onKeydown);
        this._closeCheatsheet();
    }

    /** Open the cheat-sheet from a link or button: `data-action="core--keyboard#open"`. */
    open(event) {
        event?.preventDefault?.();

        if (this._cheatsheet) {
            this._closeCheatsheet();
        } else {
            this._openCheatsheet();
        }
    }

    _readCombo(name, fallback) {
        return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') || fallback;
    }

    _onKeydown(event) {
        // ⚠️ `meta` is outside the combo vocabulary — see KeyboardComboNormalizer. Letting ⌘ combos
        // through here would steal the browser's own shortcuts on macOS.
        if (event.metaKey) {
            return;
        }

        if (event.key === 'Escape') {
            this._onEscape(event);

            return;
        }

        // While typing, only modifier combos survive.
        if (this._isEditable(event.target) && !event.ctrlKey && !event.altKey) {
            return;
        }

        // `?` is Shift+/ on most layouts, and is NOT overridable: it is the one key that explains
        // all the others, and a user who rebound it would have no way back.
        if (event.key === '?' || (event.shiftKey && event.key === '/')) {
            event.preventDefault();
            this.open(event);

            return;
        }

        const combo = this._comboFromEvent(event);

        if (!combo) {
            return;
        }

        const target = this._findTarget(combo);

        if (!target) {
            return;
        }

        event.preventDefault();

        target.click();
    }

    /**
     * `Esc` closes the cheat-sheet, and does nothing else.
     *
     * ⚠️ **It used to jump back to the list one arrived from** — only after arriving through a
     * shortcut, not overridable, and on the key every Select2, modal and native picker already owns:
     * one press too many left a half-filled form. `Ctrl+B` (`form.back`) replaced it (decider,
     * 2026-09-23), so outside the cheat-sheet `Esc` is left entirely to the browser.
     */
    _onEscape(event) {
        if (this._cheatsheet) {
            event.preventDefault();
            this._closeCheatsheet();
        }
    }

    /**
     * The canonical combo for a keystroke, or `null` when the key is not one this system fires.
     *
     * ⚠️ **The same grammar as `KeyboardComboNormalizer` on the server.** The two must agree: a
     * combo the settings screen accepts and this cannot build is a shortcut the user configured
     * and that never fires, with nothing to explain why.
     */
    _comboFromEvent(event) {
        const raw = event.key || '';

        if (['Control', 'Shift', 'Alt', 'Meta'].includes(raw)) {
            return null;
        }

        const lower = raw.toLowerCase();
        let token = null;

        if (raw === ' ') {
            token = 'space';
        } else if (lower === 'enter') {
            token = 'enter';
        } else if (/^f([1-9]|1[0-2])$/.test(lower) || /^[a-z0-9]$/.test(lower)) {
            token = lower;
        }

        if (token === null) {
            return null;
        }

        const parts = [];

        if (event.ctrlKey) parts.push('ctrl');
        if (event.shiftKey) parts.push('shift');
        if (event.altKey) parts.push('alt');

        parts.push(token);

        return parts.join('+');
    }

    _findTarget(combo) {
        for (const element of document.querySelectorAll(`[data-shortcut="${CSS.escape(combo)}"]`)) {
            if (this._isVisible(element) && !element.disabled && element.getAttribute('aria-disabled') !== 'true') {
                return element;
            }
        }

        return null;
    }

    _isEditable(node) {
        return node instanceof HTMLElement
            && (node.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(node.tagName));
    }

    _isVisible(element) {
        if (!(element instanceof HTMLElement) || element.hidden) {
            return false;
        }

        return element.offsetParent !== null || getComputedStyle(element).position === 'fixed';
    }

    // ── The cheat-sheet ───────────────────────────────────────────────────────────────────────

    _openCheatsheet() {
        this._previouslyFocused = document.activeElement;

        const global = [
            [this._combos.globalNew, this.t('keyboard.cheatsheet.global.new')],
            ['?', this.t('keyboard.cheatsheet.global.help')],
            [this._combos.formSave, this.t('keyboard.cheatsheet.global.save')],
            [this._combos.formSaveAndNew, this.t('keyboard.cheatsheet.global.save_and_new')],
            [this._combos.formBack, this.t('keyboard.cheatsheet.global.back')],
        ];

        const contextual = this._collectContextual();

        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[100] flex items-center justify-center p-4';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-labelledby', 'kb-cheatsheet-title');
        overlay.innerHTML = `
            <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" data-kb-backdrop></div>
            <div class="relative w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-700 dark:bg-slate-800">
                <div class="mb-4 flex items-start justify-between">
                    <h2 id="kb-cheatsheet-title" class="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        <i class="fa-solid fa-keyboard mr-2 text-accent-500"></i>${this._escape(this.t('keyboard.cheatsheet.title'))}
                    </h2>
                    <button type="button" data-kb-close aria-label="${this._escape(this.t('keyboard.cheatsheet.close'))}"
                            class="text-slate-500 hover:text-slate-800 dark:hover:text-slate-200">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>
                ${this._section(this.t('keyboard.cheatsheet.section.global'), global)}
                ${contextual.length > 0 ? this._section(this.t('keyboard.cheatsheet.section.page'), contextual) : ''}
            </div>
        `;

        overlay.querySelector('[data-kb-backdrop]').addEventListener('click', () => this._closeCheatsheet());
        overlay.querySelector('[data-kb-close]').addEventListener('click', () => this._closeCheatsheet());

        document.body.appendChild(overlay);
        this._cheatsheet = overlay;
        overlay.querySelector('[data-kb-close]')?.focus();
    }

    _closeCheatsheet() {
        if (!this._cheatsheet) {
            return;
        }

        this._cheatsheet.remove();
        this._cheatsheet = null;

        if (this._previouslyFocused instanceof HTMLElement) {
            this._previouslyFocused.focus();
        }

        this._previouslyFocused = null;
    }

    _section(title, rows) {
        return `
            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">${this._escape(title)}</h3>
            <table class="mb-4 w-full text-sm">
                ${rows.map(([combo, label]) => `
                    <tr>
                        <td class="w-1/3 py-1.5 pr-4">${this._renderCombo(combo)}</td>
                        <td class="py-1.5 text-slate-600 dark:text-slate-300">${this._escape(label)}</td>
                    </tr>
                `).join('')}
            </table>
        `;
    }

    /**
     * ⚠️ Only elements carrying BOTH a combo and a label are listed. A shortcut without a label
     * fires and stays out of the cheat-sheet — which is a shortcut nobody can discover, and the
     * reason `data-shortcut-label` is documented as mandatory rather than optional.
     */
    _collectContextual() {
        const rows = [];
        const seen = new Set();
        // A combo already in the global section is not listed twice: the Cancel link Ctrl+B clicks
        // carries a label of its own, and so does the "New" button `n` clicks.
        const globalCombos = new Set(Object.values(this._combos || {}));

        for (const element of document.querySelectorAll('[data-shortcut][data-shortcut-label]')) {
            const combo = element.getAttribute('data-shortcut');
            const label = element.getAttribute('data-shortcut-label');

            if (!combo || !label || globalCombos.has(combo) || !this._isVisible(element) || seen.has(`${combo}::${label}`)) {
                continue;
            }

            seen.add(`${combo}::${label}`);
            rows.push([combo, label]);
        }

        return rows;
    }

    _renderCombo(combo) {
        const names = { ctrl: 'Ctrl', shift: 'Shift', alt: 'Alt', enter: 'Enter', space: 'Space', esc: 'Esc' };

        return String(combo)
            .split('+')
            .map((part) => `<kbd class="inline-block rounded border border-slate-300 bg-slate-100 px-1.5 py-0.5 font-mono text-xs shadow-sm dark:border-slate-600 dark:bg-slate-700">${this._escape(names[part.toLowerCase()] || part.toUpperCase())}</kbd>`)
            .join('<span class="mx-1 text-slate-400">+</span>');
    }

    _escape(value) {
        const node = document.createElement('div');
        node.textContent = String(value ?? '');

        return node.innerHTML;
    }
}
