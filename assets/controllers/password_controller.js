import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["input", "toggle"]

    connect() {
        this.showing = false;
        this._resetDOM();
    }

    disconnect() {
        this.showing = false;
        this._resetDOM();
    }

    toggle() {
        if (!this.hasInputTarget) return;

        this.showing = !this.showing;
        this.inputTarget.type = this.showing ? "text" : "password";

        const icon = this.inputTarget.parentElement.querySelector('i.fa-eye, i.fa-eye-slash');
        if (icon) {
            icon.classList.toggle('fa-eye', !this.showing);
            icon.classList.toggle('fa-eye-slash', this.showing);
        }
    }

    _resetDOM() {
        if (this.hasInputTarget) {
            this.inputTarget.type = "password";
        }
    }
}
