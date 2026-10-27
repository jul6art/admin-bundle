import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu'];

    connect() {
        this.onDocumentClick = this.onDocumentClick.bind(this);
        document.addEventListener('click', this.onDocumentClick);
    }

    disconnect() {
        document.removeEventListener('click', this.onDocumentClick);
    }

    toggle(event) {
        event.preventDefault();
        event.stopPropagation();
        if (!this.hasMenuTarget) return;
        const open = this.menuTarget.classList.toggle('hidden') === false;
        if (open) this._align();
        // The button says whether its menu is open (1.35) — a reader has no other way to know.
        event.currentTarget?.setAttribute?.('aria-expanded', String(open));
    }

    close() {
        if (!this.hasMenuTarget) return;
        this.menuTarget.classList.add('hidden');
        this.element.querySelectorAll('[aria-expanded="true"]').forEach((button) => {
            button.setAttribute('aria-expanded', 'false');
        });
    }

    onDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    /**
     * The menu hangs from its button's RIGHT edge (1.35.1): right for a record's header, whose actions
     * sit on the right. Once they wrap under the title — a list's preview sheet of 28rem, a phone —
     * the button can stand on the left, and the menu went out of the sheet by its left side, its labels
     * cut (« …es guides », seen on devingame's game record, 2026-10-27). It then hangs from the LEFT
     * edge. Inline styles, not a class: a class set from JavaScript alone is one Tailwind never reads.
     */
    _align() {
        const menu = this.menuTarget;
        menu.style.left = '';
        menu.style.right = '';
        if (menu.getBoundingClientRect().left < this._clipLeft()) {
            menu.style.left = '0';
            menu.style.right = 'auto';
        }
    }

    /** The left edge the menu must not cross: its first clipping ancestor's (the sheet), else the window's. */
    _clipLeft() {
        for (let node = this.element.parentElement; node && node !== document.body; node = node.parentElement) {
            if (getComputedStyle(node).overflowX !== 'visible') {
                return node.getBoundingClientRect().left;
            }
        }

        return 0;
    }
}
