import { Controller } from '@hotwired/stimulus';

/**
 * The inbox (`@Admin/inbox/_layout.html.twig`, 1.25): a page the server renders — one per
 * folder, one per open message, links everywhere. This controller only helps.
 *
 * ```twig
 * {% embed '@Admin/inbox/_layout.html.twig' with {inbox: inbox} %}
 *     {% block inbox_reader %}…{% endblock %}
 * {% endembed %}
 * ```
 *
 * - **Relative dates**: every `<time data-ui--inbox-target="date">` reads "3 hours ago" in the
 *   page's language (`<html lang>`, `Intl.RelativeTimeFormat`); the absolute date the server wrote
 *   moves to `title`. Without JavaScript, the absolute date stays.
 * - **`refresh()`**: reloads the list and the folders from the current URL, in place. The bundle
 *   owns no real-time feed: the application calls this action when its own (Mercure) says a
 *   message arrived — `data-action="…->ui--inbox#refresh"`.
 * - **A link to a field** (`<a href="#reply-body">`) focuses it, so a shortcut that "clicks" the
 *   reply link (`inbox.reply`) lands the caret in the box, not just the viewport on it.
 *
 * ⚠️ `j`, `k`, `r` and `e` are NOT handled here: they are visible links and buttons carrying
 * `data-shortcut`, which the keyboard router clicks — a button a permission withholds is a
 * shortcut that does not exist.
 */
export default class extends Controller {
    static targets = ['list', 'folders', 'date'];

    connect() {
        this._relative = new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
        this._onClick = this._onClick.bind(this);
        this.element.addEventListener('click', this._onClick);
        this._humanize();

        // The open message stays in sight in a long list.
        if (this.hasListTarget) {
            this.listTarget.querySelector('[aria-current="true"]')?.scrollIntoView({ block: 'nearest' });
        }
    }

    disconnect() {
        this.element.removeEventListener('click', this._onClick);
    }

    /**
     * The list and the folders, as the server renders them now.
     */
    async refresh() {
        const response = await fetch(window.location.href, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!response.ok) {
            return;
        }

        const page = new DOMParser().parseFromString(await response.text(), 'text/html');

        for (const name of ['list', 'folders']) {
            const fresh = page.querySelector(`[data-ui--inbox-target="${name}"]`);
            const hasTarget = this[`has${name.charAt(0).toUpperCase()}${name.slice(1)}Target`];

            if (fresh && hasTarget) {
                this[`${name}Target`].innerHTML = fresh.innerHTML;
            }
        }

        this._humanize();
    }

    _onClick(event) {
        const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
        const field = link ? document.getElementById(link.getAttribute('href').slice(1)) : null;

        if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) {
            event.preventDefault();
            field.focus();
        }
    }

    _humanize() {
        const now = Date.now();

        for (const time of this.dateTargets) {
            const at = Date.parse(time.getAttribute('datetime') ?? '');

            if (Number.isNaN(at)) {
                continue;
            }

            if (!time.title) {
                time.title = time.textContent.trim();
            }

            time.textContent = this._format((at - now) / 1000);
        }
    }

    _format(seconds) {
        const units = [
            ['year', 31536000],
            ['month', 2592000],
            ['week', 604800],
            ['day', 86400],
            ['hour', 3600],
            ['minute', 60],
        ];

        for (const [unit, size] of units) {
            if (Math.abs(seconds) >= size) {
                return this._relative.format(Math.round(seconds / size), unit);
            }
        }

        return this._relative.format(0, 'minute');
    }
}
