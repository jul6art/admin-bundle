import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel'];

    connect() {
        this.onWindowResize = this.closeOnLargeScreen.bind(this);
        this.onWindowClick = this.closeOnWindowClick.bind(this);

        window.addEventListener('resize', this.onWindowResize);
        window.addEventListener('click', this.onWindowClick);

        this.closeOnLargeScreen();
    }

    disconnect() {
        window.removeEventListener('resize', this.onWindowResize);
        window.removeEventListener('click', this.onWindowClick);
    }

    toggle() {
        if (!this.hasPanelTarget) return;
        this.panelTarget.classList.toggle('-translate-x-full');
    }

    close() {
        if (!this.hasPanelTarget) return;
        this.panelTarget.classList.add('-translate-x-full');
    }

    closeOnLargeScreen() {
        if (!this.hasPanelTarget) return;
        if (window.matchMedia('(min-width: 1024px)').matches) {
            this.panelTarget.classList.remove('-translate-x-full');
        }
    }

    closeOnWindowClick(event) {
        if (!this.hasPanelTarget || window.matchMedia('(min-width: 1024px)').matches) return;

        const clickedToggle = event.target.closest('[data-sidebar-toggle]');
        if (clickedToggle || this.panelTarget.contains(event.target)) return;

        this.close();
    }
}
