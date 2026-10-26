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
}
