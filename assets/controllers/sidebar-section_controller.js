import { Controller } from '@hotwired/stimulus';

/**
 * Sidebar section — collapsible group with active-aware default state.
 *
 * Used for both level 1 (top sections like CMS / CRM / ERP) and level 2
 * (sub-sections inside a level-1 group, e.g. ERP > Invoicing). Each
 * instance is independent; nesting them composes a 2-level menu.
 *
 * Open-state logic on `connect()`:
 *   1. Has the section been manually toggled by the user before? Use that
 *      value (read from localStorage, scoped to the section's key).
 *   2. Otherwise, is this section the one that holds the currently active
 *      page? (`data-ui--sidebar-section-active-value="true"`) → open.
 *   3. Otherwise → closed.
 *
 * Storage key: `sidebar.section.<key>` so different sections persist
 * independently. Cleared globally if the convention ever changes by
 * incrementing a prefix in code.
 *
 * Markup:
 *   <div data-controller="ui--sidebar-section"
 *        data-ui--sidebar-section-key-value="cms"
 *        data-ui--sidebar-section-active-value="true|false">
 *     <button data-action="click->ui--sidebar-section#toggle">
 *       <span>CMS</span>
 *       <i data-ui--sidebar-section-target="chevron"></i>
 *     </button>
 *     <ul data-ui--sidebar-section-target="content" class="hidden">…</ul>
 *   </div>
 */
export default class extends Controller {
    static targets = ['content', 'chevron'];
    static values = {
        key: String,
        active: { type: Boolean, default: false },
    };

    connect() {
        // Resolve initial open state via the priority chain documented above.
        const stored = this._readStored();
        this._open = (null !== stored) ? stored : this.activeValue;
        this._render();
    }

    toggle(event) {
        if (event) event.preventDefault();
        this._open = !this._open;
        this._writeStored(this._open);
        this._render();
    }

    _render() {
        this.contentTargets.forEach((el) => el.classList.toggle('hidden', !this._open));
        this.chevronTargets.forEach((el) => {
            el.style.transform = this._open ? 'rotate(180deg)' : 'rotate(0deg)';
        });
    }

    _storageKey() {
        return `sidebar.section.${this.keyValue || 'unnamed'}`;
    }

    _readStored() {
        try {
            const raw = window.localStorage.getItem(this._storageKey());
            if (raw === '1') return true;
            if (raw === '0') return false;
        } catch (_) {
            // localStorage may be unavailable (Safari private mode, sandboxed iframe).
        }
        return null;
    }

    _writeStored(open) {
        try {
            window.localStorage.setItem(this._storageKey(), open ? '1' : '0');
        } catch (_) {
            // ignore — persistence is best-effort
        }
    }
}
