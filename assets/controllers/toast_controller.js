import { Controller } from '@hotwired/stimulus';

const COLOR_CLASSES = {
    success: 'bg-emerald-50 border-emerald-200 text-emerald-700',
    error:   'bg-red-50 border-red-200 text-red-700',
    warning: 'bg-amber-50 border-amber-200 text-amber-700',
    info:    'bg-accent-50 border-accent-200 text-accent-700',
};

const AUTO_DISMISS_DELAY = 3500;

export default class extends Controller {
    static targets = ['item'];

    connect() {
        this._onToastEvent = (event) => this._addToast(event.detail.message, event.detail.type ?? 'info');
        document.addEventListener('ui:toast', this._onToastEvent);
        this.itemTargets.forEach((item) => this._scheduleAutoDismiss(item));
    }

    disconnect() {
        document.removeEventListener('ui:toast', this._onToastEvent);
    }

    close(event) {
        const item = event.currentTarget.closest('[data-ui--toast-target="item"]');
        this._dismiss(item);
    }

    // --- private ---

    _addToast(message, type) {
        const colors = COLOR_CLASSES[type] ?? COLOR_CLASSES.info;
        const closeLabel = this.element.dataset.uiToastCloseLabel ?? 'Close';

        const item = document.createElement('div');
        item.setAttribute('data-ui--toast-target', 'item');
        item.dataset.type = type;
        if (type === 'success') {
            item.dataset.autodismiss = '1';
        }
        item.className = `pointer-events-auto flex items-center justify-between gap-3 px-4 py-3 rounded-xl border shadow-lg text-sm transition-all duration-200 ${colors}`;
        item.innerHTML = `
            <span>${message}</span>
            <button type="button"
                    data-action="ui--toast#close"
                    class="ml-2 flex-shrink-0 text-current opacity-60 hover:opacity-100 transition-opacity"
                    aria-label="${closeLabel}">✕</button>
        `;

        this.element.appendChild(item);
        this._scheduleAutoDismiss(item);
    }

    _scheduleAutoDismiss(item) {
        if (!item.dataset.autodismiss) {
            return;
        }
        window.setTimeout(() => this._dismiss(item), AUTO_DISMISS_DELAY);
    }

    _dismiss(item) {
        if (!item) {
            return;
        }
        item.classList.add('opacity-0', 'translate-y-1');
        window.setTimeout(() => item.remove(), 220);
    }
}

