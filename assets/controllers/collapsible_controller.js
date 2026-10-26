import { Controller } from '@hotwired/stimulus';

/**
 * Collapsible Controller — Toggle content visibility.
 *
 * Usage:
 *   <div data-controller="ui--collapsible" data-ui--collapsible-open-value="false">
 *       <button data-action="ui--collapsible#toggle" data-ui--collapsible-target="trigger" aria-expanded="false">
 *           Title <i data-ui--collapsible-target="icon" class="fa-solid fa-chevron-down"></i>
 *       </button>
 *       <div data-ui--collapsible-target="content" class="hidden">
 *           ...content...
 *       </div>
 *   </div>
 *
 * Every `trigger` target carries `aria-expanded` (1.35): a reader hears whether the content it
 * opens is open, which a rotating chevron alone never says.
 */
export default class extends Controller {
    static targets = ['content', 'icon', 'trigger'];
    static values = { open: { type: Boolean, default: false } };

    connect() {
        this._update();
    }

    toggle() {
        this.openValue = !this.openValue;
        this._update();
    }

    _update() {
        this.contentTargets.forEach(el => {
            el.classList.toggle('hidden', !this.openValue);
        });
        this.iconTargets.forEach(icon => {
            icon.style.transform = this.openValue ? 'rotate(180deg)' : 'rotate(0deg)';
        });
        this.triggerTargets.forEach(trigger => {
            trigger.setAttribute('aria-expanded', String(this.openValue));
        });
    }
}
