import { Controller } from '@hotwired/stimulus';

// Below this room, a title cut to a few letters names nothing: it shows only if it fits whole.
const MIN_ROOM = 96;

/**
 * The page title in the top bar (1.29): once the page's `<h1>` has scrolled out of sight, its text
 * shows in the top bar, after the left slot — down a long form, the name of the record being edited
 * stays on screen. It leaves as soon as the `<h1>` comes back.
 *
 * Mounted by the shell (`layout.html.twig`) on the column that holds the top bar and `<main>`; a
 * project only registers it under `ui--page-title`:
 *
 * ```js
 * // assets/controllers/ui/page-title_controller.js
 * export { default } from '@jul6art/admin-bundle/controllers/page-title_controller';
 * ```
 *
 * - **What it copies**: the first `<h1>` of `<main>`, or the element a page marks
 *   `data-ui--page-title-target="source"` when its `<h1>` does not carry the right name.
 * - **When**: an `IntersectionObserver` rooted on `<main>`, which is what scrolls in the shell —
 *   not a scroll threshold. A short page never loses its `<h1>`, so nothing ever shows there: the
 *   behaviour needs no switch.
 * - **Where**: out of the flow, over the free space between the left slot and the first thing of
 *   the centre slot (the search field, its magnifier on a phone), or the end of that slot when it is
 *   empty. Cut with an ellipsis, the full text in `title`; hidden when it neither fits whole nor
 *   has `MIN_ROOM` — on a phone with a search magnifier, a short name still shows.
 * - `aria-hidden`: the `<h1>` is already read, the copy is for the eyes.
 *
 * ⚠️ **Never in the flow.** A title that took its room in the bar shrank the centre slot, and the
 * search field, centred in it, slid sideways under the cursor each time the title came and went.
 */
export default class extends Controller {
    static targets = ['label', 'start', 'limit', 'scroller', 'source'];

    connect() {
        this._source = this.hasSourceTarget ? this.sourceTarget : this.hasScrollerTarget ? this.scrollerTarget.querySelector('h1') : null;

        if (!this._source || !this.hasLabelTarget || !('IntersectionObserver' in window)) {
            return;
        }

        this._place = this._place.bind(this);
        this._observer = new IntersectionObserver(
            ([entry]) => {
                // Gone UPWARDS only: a title still below the fold has not been read yet.
                this._shown = !entry.isIntersecting && entry.boundingClientRect.bottom <= (entry.rootBounds?.top ?? 0);
                this._place();
            },
            { root: this.hasScrollerTarget ? this.scrollerTarget : null },
        );
        this._observer.observe(this._source);
        window.addEventListener('resize', this._place);
    }

    disconnect() {
        this._observer?.disconnect();
        window.removeEventListener('resize', this._place);
    }

    _place() {
        const label = this.labelTarget;
        const bar = label.offsetParent;

        if (!this._shown || !bar) {
            this._show(false);
            return;
        }

        const text = this._source.textContent.replace(/\s+/g, ' ').trim();
        const gap = Number.parseFloat(getComputedStyle(bar).columnGap) || 0;
        const barBox = bar.getBoundingClientRect();
        const startBox = this.hasStartTarget ? this.startTarget.getBoundingClientRect() : null;
        // An empty left slot (no menu button from `lg` up) has no width: the title starts where it does.
        const left = startBox ? (startBox.width > 0 ? startBox.right + gap : startBox.left) : barBox.left;
        const first = this.hasLimitTarget ? [...this.limitTarget.children].find((child) => child.getClientRects().length > 0) : null;
        const right = first ? first.getBoundingClientRect().left - gap : this.hasLimitTarget ? this.limitTarget.getBoundingClientRect().right : barBox.right;
        const room = Math.floor(right - left);

        label.textContent = text;
        label.style.maxWidth = '';

        if (text === '' || room < Math.min(label.scrollWidth, MIN_ROOM)) {
            this._show(false);
            return;
        }

        label.title = text;
        label.style.left = `${left - barBox.left}px`;
        label.style.maxWidth = `${room}px`;
        this._show(true);
    }

    _show(visible) {
        this.labelTarget.classList.toggle('opacity-0', !visible);
        this.labelTarget.classList.toggle('opacity-100', visible);
    }
}
