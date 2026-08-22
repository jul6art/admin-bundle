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
        this.menuTarget.classList.toggle('hidden');
    }

    close() {
        if (!this.hasMenuTarget) return;
        this.menuTarget.classList.add('hidden');
    }

    onDocumentClick(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }
}
