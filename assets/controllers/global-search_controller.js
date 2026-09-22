import { Controller } from '@hotwired/stimulus';

/**
 * The header's global search — register it as `search--global`, the identifier
 * `partials/_global_search.html.twig` addresses.
 *
 * It moved here from two products that carried it as twins (cereezer ADR-0031, superp ADR-0012):
 * not one of its lines is about either of them. What is searched, and who may see it, stays in each
 * product, behind the route this controller calls.
 *
 * ## Nothing is translated here
 *
 * Group headings, the empty line and the "N results" hint arrive ALREADY rendered, as Stimulus
 * values. Translating in the browser would mean shipping a catalogue for a few words the server
 * already knows — and a missing one would show as a raw key.
 *
 * ## A debounce, and a threshold
 *
 * Without the 250 ms debounce, a word typed at normal speed fires one request per keystroke, each
 * costing several queries on the server. The two-character threshold is doubled on the server: this
 * file can be bypassed, the route cannot.
 *
 * ## The request in flight is ABORTED
 *
 * Two close keystrokes fire two requests; without an `AbortController` the slower may answer last
 * and overwrite the results of the latest keystroke — a panel showing a term already erased.
 *
 * ## Below `md`, a row that opens and closes
 *
 * The field is replaced by a magnifier; `open()` shows a full-width row below the header and
 * focuses the field. Escape, the × and a tap outside close it (`dismiss()`). From `md` up the row is
 * the plain inline field and `open()` is never called.
 */
export default class extends Controller {
    static targets = ['input', 'panel', 'bar', 'toggle'];

    static values = {
        url: String,
        labels: Object,
        empty: String,
        more: String,
    };

    connect() {
        this.timer = null;
        this.pending = null;
        this.onKeydown = (event) => this.#shortcut(event);
        this.onOutside = (event) => {
            if (!this.element.contains(event.target)) {
                this.dismiss();
            }
        };
        document.addEventListener('keydown', this.onKeydown);
        document.addEventListener('pointerdown', this.onOutside);
    }

    disconnect() {
        document.removeEventListener('keydown', this.onKeydown);
        document.removeEventListener('pointerdown', this.onOutside);
        window.clearTimeout(this.timer);
        this.pending?.abort();
    }

    search() {
        window.clearTimeout(this.timer);
        this.timer = window.setTimeout(() => this.#run(), 250);
    }

    close() {
        // Deferred: a click ON a result fires `blur` before `click`. Closing at once would remove the
        // link from the DOM before the browser followed it.
        window.setTimeout(() => this.panelTarget.setAttribute('hidden', 'hidden'), 150);
    }

    /**
     * Opens the mobile row and focuses its field.
     */
    open() {
        if (this.hasBarTarget) {
            this.barTarget.classList.remove('hidden');
        }
        this.inputTarget.focus();
    }

    /**
     * Closes the result panel and, below `md`, the row itself. Harmless from `md` up: the row is
     * `md:block`, so removing `hidden` or adding it back changes nothing there.
     */
    dismiss() {
        this.panelTarget.setAttribute('hidden', 'hidden');
        if (this.hasBarTarget && this.hasToggleTarget && this.#isMobile()) {
            this.barTarget.classList.add('hidden');
        }
    }

    async #run() {
        const term = this.inputTarget.value.trim();

        if (term.length < 2) {
            this.panelTarget.setAttribute('hidden', 'hidden');

            return;
        }

        this.pending?.abort();
        this.pending = new AbortController();

        try {
            const response = await fetch(`${this.urlValue}?q=${encodeURIComponent(term)}`, {
                signal: this.pending.signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            this.#render(await response.json());
        } catch (error) {
            // An aborted request is not an error: it is the intended behaviour.
            if (error.name !== 'AbortError') {
                console.error('[search] ', error);
            }
        }
    }

    #render(groups) {
        const keys = Object.keys(groups);

        if (keys.length === 0) {
            this.panelTarget.innerHTML = `<p class="px-4 py-3 text-sm text-slate-500">${this.#escape(this.emptyValue)}</p>`;
            this.panelTarget.removeAttribute('hidden');

            return;
        }

        this.panelTarget.innerHTML = keys
            .map((key) => {
                const group = groups[key];
                const rows = group.results
                    .map(
                        (result) =>
                            `<a href="${this.#escape(result.url)}" class="block px-4 py-2 text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700">${this.#escape(result.label)}</a>`,
                    )
                    .join('');

                // The total is recalled only when it EXCEEDS what is shown: "3 of 3" is noise,
                // "5 of 47" is what sends the user to the full screen.
                const more =
                    group.total > group.results.length
                        ? `<span class="text-xs text-slate-400">${this.#escape(this.moreValue.replace('%total%', group.total))}</span>`
                        : '';

                return `<div class="py-1">
                    <div class="px-4 pt-2 pb-1 flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">${this.#escape(this.labelsValue[key] ?? key)}</span>
                        ${more}
                    </div>
                    ${rows}
                </div>`;
            })
            .join('');

        this.panelTarget.removeAttribute('hidden');
    }

    /**
     * ⚠️ A label comes from the DATABASE: a customer named `<script>` would be injected as is into
     * `innerHTML`. Not theoretical — a company name is free text.
     */
    #escape(value) {
        const node = document.createElement('span');
        node.textContent = String(value);

        return node.innerHTML;
    }

    #isMobile() {
        return window.getComputedStyle(this.toggleTarget).display !== 'none';
    }

    #shortcut(event) {
        if (event.key !== '/' || event.metaKey || event.ctrlKey) {
            return;
        }

        // Ignored when the focus is already in a field: otherwise typing `/` in a description would
        // open the search instead of writing a character.
        const active = document.activeElement;

        if (active && ['INPUT', 'TEXTAREA', 'SELECT'].includes(active.tagName)) {
            return;
        }

        if (active?.isContentEditable) {
            return;
        }

        event.preventDefault();
        this.open();
    }
}
