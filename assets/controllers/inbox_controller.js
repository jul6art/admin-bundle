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
 * - **Older messages (1.28)**: the "older" link the template draws under the list (`more_url`) is
 *   followed as soon as it scrolls into view — the next slice is fetched and APPENDED, and the link
 *   replaced by the slice's own, so the list scrolls on. The link stays a link: the keyboard
 *   presses it (`more`), the focus moves to the first message added, and a screen reader hears
 *   how many came (`loaded`, `%count%`).
 *
 * - **The panes fit the screen (1.28.1)**: the list and the open message scroll each in their own
 *   pane, never the page. `--admin-inbox-top` is set to what stands above them (title, folders…),
 *   measured here and again on resize; the stylesheet falls back to 16rem without JavaScript.
 *
 * ⚠️ **`refresh()` keeps what was loaded.** Once older slices are in, the fresh first page
 * replaces the HEAD of the list — down to its own oldest message — and the rest stays. Replacing
 * the whole list threw away every slice the user had scrolled through, each time a message
 * arrived.
 *
 * ⚠️ `j`, `k`, `r` and `e` are NOT handled here: they are visible links and buttons carrying
 * `data-shortcut`, which the keyboard router clicks — a button a permission withholds is a
 * shortcut that does not exist.
 */
export default class extends Controller {
    static targets = ['list', 'folders', 'date', 'more', 'status'];

    static values = {
        // What a screen reader hears once a slice is in; `%count%` is the number of messages added.
        loaded: { type: String, default: '' },
    };

    connect() {
        this._relative = new Intl.RelativeTimeFormat(document.documentElement.lang || undefined, { numeric: 'auto' });
        this._onClick = this._onClick.bind(this);
        this.element.addEventListener('click', this._onClick);
        this._humanize();

        this._fit = this._fit.bind(this);
        this._fit();
        window.addEventListener('resize', this._fit);

        // The open message stays in sight in a long list.
        if (this.hasListTarget) {
            this.listTarget.querySelector('[aria-current="true"]')?.scrollIntoView({ block: 'nearest' });
        }
    }

    disconnect() {
        this.element.removeEventListener('click', this._onClick);
        window.removeEventListener('resize', this._fit);
        this._observer?.disconnect();
        this._observer = null;
    }

    /**
     * What stands above the panes — from the top of the screen, as if the page were scrolled to its
     * top — and below them, down to the end of the page (its bottom padding): the panes take the
     * rest, and the page no longer scrolls.
     */
    _fit() {
        const panes = this.element.querySelector('.admin-inbox__panes');
        const scroller = this.element.closest('main') ?? document.scrollingElement;

        if (!panes || !scroller) {
            return;
        }

        const box = panes.getBoundingClientRect();
        const origin = scroller === document.scrollingElement ? 0 : scroller.getBoundingClientRect().top;
        const top = box.top + scroller.scrollTop;
        const below = scroller.scrollHeight - (box.bottom - origin + scroller.scrollTop);
        this.element.style.setProperty('--admin-inbox-top', `${Math.max(0, Math.round(top + below))}px`);
    }

    moreTargetConnected(element) {
        if (!('IntersectionObserver' in window)) {
            return;
        }

        // Created on first use: a target can connect before `connect()` runs. The root is the list,
        // which scrolls in its own pane: the margin then loads the next slice a little before its
        // link shows.
        this._observer ??= new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    this.more();
                }
            },
            { root: this.hasListTarget ? this.listTarget : null, rootMargin: '0px 0px 300px 0px' },
        );
        this._observer.observe(element);
    }

    moreTargetDisconnected(element) {
        this._observer?.unobserve(element);
    }

    /**
     * The next, older slice of the folder, appended to the list.
     */
    async more(event) {
        event?.preventDefault();

        const sentinel = this.hasMoreTarget ? this.moreTarget : null;
        const link = sentinel?.querySelector('a[href]');
        const list = this.hasListTarget ? this.listTarget.querySelector('.admin-inbox__items') : null;

        if (!link || !list || this._loading) {
            return;
        }

        this._loading = true;
        sentinel.setAttribute('aria-busy', 'true');
        const fromKeyboard = document.activeElement === link;

        try {
            const fresh = await this._fetchList(link.href);

            if (!fresh) {
                return;
            }

            const known = new Set([...list.children].map((item) => this._hrefOf(item)));
            const added = [...fresh.querySelectorAll('.admin-inbox__items > li')]
                .filter((item) => !known.has(this._hrefOf(item)))
                .map((item) => document.importNode(item, true));

            list.append(...added);

            const next = fresh.querySelector('[data-ui--inbox-target="more"]');
            if (next) {
                sentinel.replaceWith(document.importNode(next, true));
            } else {
                sentinel.remove();
            }

            this._slices = (this._slices ?? 0) + 1;
            this._humanize();
            this._announce(added.length);

            if (fromKeyboard) {
                added[0]?.querySelector('a')?.focus();
            }
        } finally {
            this._loading = false;
            sentinel.removeAttribute('aria-busy');
        }
    }

    /**
     * The list and the folders, as the server renders them now.
     */
    async refresh() {
        const page = await this._fetchPage(window.location.href);

        if (!page) {
            return;
        }

        const folders = page.querySelector('[data-ui--inbox-target="folders"]');
        if (folders && this.hasFoldersTarget) {
            this.foldersTarget.innerHTML = folders.innerHTML;
        }

        const list = page.querySelector('[data-ui--inbox-target="list"]');
        if (list && this.hasListTarget && !(this._slices && this._mergeHead(list))) {
            this.listTarget.innerHTML = list.innerHTML;
            this._slices = 0;
        }

        this._humanize();
    }

    /**
     * The fresh first page in place of the head of the list, down to its oldest message; the older
     * slices loaded below stay. `false` when that message is not in the list — the caller then
     * replaces everything.
     */
    _mergeHead(fresh) {
        const list = this.listTarget.querySelector('.admin-inbox__items');
        const items = [...fresh.querySelectorAll('.admin-inbox__items > li')];

        if (!list || items.length === 0) {
            return false;
        }

        const oldest = this._hrefOf(items[items.length - 1]);
        const current = [...list.children];
        const cut = current.findIndex((item) => this._hrefOf(item) === oldest);

        if (cut < 0) {
            return false;
        }

        current.slice(0, cut + 1).forEach((item) => item.remove());
        list.prepend(...items.map((item) => document.importNode(item, true)));

        return true;
    }

    async _fetchPage(url) {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        return response.ok ? new DOMParser().parseFromString(await response.text(), 'text/html') : null;
    }

    async _fetchList(url) {
        return (await this._fetchPage(url))?.querySelector('[data-ui--inbox-target="list"]') ?? null;
    }

    _hrefOf(item) {
        return item.querySelector('a')?.getAttribute('href') ?? null;
    }

    _announce(count) {
        if (this.hasStatusTarget && this.loadedValue) {
            this.statusTarget.textContent = this.loadedValue.replace('%count%', String(count));
        }
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
