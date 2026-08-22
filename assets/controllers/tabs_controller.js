import { Controller } from '@hotwired/stimulus';

/**
 * Tabs Controller — Simple tab switcher.
 *
 * Usage:
 *   <div data-controller="ui--tabs">
 *       <button data-action="ui--tabs#select" data-ui--tabs-index-param="0">Tab 1</button>
 *       <button data-action="ui--tabs#select" data-ui--tabs-index-param="1">Tab 2</button>
 *       <div data-ui--tabs-target="panel">Content 1</div>
 *       <div data-ui--tabs-target="panel" class="hidden">Content 2</div>
 *   </div>
 *
 * On `connect()`, if a panel other than the active one contains a form
 * error (`.form-error`, `.form-control-error`, `.dropzone-area-error`),
 * the first such panel is selected. This makes server-side validation
 * errors visible after a failed submission, even when the offending
 * field lives in a non-default tab (typical for the multi-locale CMS
 * forms where one translation tab can fail while another is valid).
 */
export default class extends Controller {
    static targets = ['panel'];

    connect() {
        this._activateFirstPanelWithError();
    }

    select({ params: { index } }) {
        this._activate(index);
    }

    _activate(index) {
        // Update panels
        this.panelTargets.forEach((panel, i) => {
            panel.classList.toggle('hidden', i !== index);
        });

        // Update tab buttons (visual state + ARIA so the locale switcher
        // logic in `cms--preview` can still locate the active tab).
        const buttons = this.element.querySelectorAll('[data-action*="ui--tabs#select"]');
        buttons.forEach((btn, i) => {
            const isActive = i === index;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            if (isActive) {
                btn.classList.add('border-accent-600', 'text-accent-700');
                btn.classList.remove('text-slate-500', 'border-transparent');
            } else {
                btn.classList.remove('border-accent-600', 'text-accent-700');
                btn.classList.add('text-slate-500', 'border-transparent');
            }
        });

        // Adjust DataTables columns in newly visible panel (fixes colspan on hidden tables)
        const activePanel = this.panelTargets[index];
        if (activePanel && window.DataTable) {
            activePanel.querySelectorAll('table').forEach((table) => {
                if (window.DataTable.isDataTable(table)) {
                    window.DataTable.Api(table).columns.adjust();
                }
            });
        }
    }

    /**
     * After a failed form submission, the offending field may live in a
     * tab that's not the default one — the user would otherwise see no
     * visible error. Scan panels in declaration order and switch to the
     * first one containing an error marker.
     */
    _activateFirstPanelWithError() {
        const errorIndex = this.panelTargets.findIndex((panel) => {
            return (
                panel.querySelector('.form-error') !== null ||
                panel.querySelector('.form-control-error') !== null ||
                panel.querySelector('.dropzone-area-error') !== null
            );
        });

        if (errorIndex > 0) {
            this._activate(errorIndex);
        }
    }
}
