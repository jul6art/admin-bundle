import { Controller } from '@hotwired/stimulus';

/**
 * Theme Controller — dark/light mode toggle (menu button).
 *
 * The per-user mode is applied SERVER-SIDE (the <html> data-theme + the
 * anti-FOUC head script). This controller only:
 *   - keeps localStorage in sync with the current user's value on connect
 *     (so a previous user's choice never leaks), and
 *   - flips light/dark and persists to the server when the button is clicked.
 *
 * It deliberately does NOT re-apply the mode on connect (that would override
 * the head script's `system` decision); it just refreshes the icon.
 */
export default class extends Controller {
    static values = {
        current: { type: String, default: 'light' },
        saveUrl: String,
    };

    connect() {
        // Server preference wins; resync the browser-global cache so the
        // toggle and the anti-FOUC script agree for this user.
        if (this.hasCurrentValue && this.currentValue !== 'system') {
            localStorage.setItem('theme', this.currentValue === 'dark' ? 'dark' : 'light');
        }
        this._updateIcon(document.documentElement.classList.contains('dark'));
    }

    toggle() {
        const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
        document.documentElement.classList.toggle('dark', next === 'dark');
        this._updateIcon(next === 'dark');
        localStorage.setItem('theme', next);
        this._save(next);
    }

    _updateIcon(isDark) {
        const icon = this.element.querySelector('[data-theme-icon]');
        if (icon) {
            icon.className = isDark
                ? 'fa-solid fa-sun text-amber-400'
                : 'fa-solid fa-moon text-slate-400';
        }
    }

    async _save(theme) {
        if (!this.hasSaveUrlValue) return;
        try {
            await fetch(this.saveUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ theme }),
            });
        } catch (e) {
            // silently fail
        }
    }
}
