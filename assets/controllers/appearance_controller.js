import { Controller } from '@hotwired/stimulus';

/**
 * Applies the appearance form to the document as the user tweaks it, so the whole interface is the
 * preview. Saving is the form's POST; this only mirrors the colour mode into localStorage so the
 * anti-FOUC script in the head stays in step.
 *
 * Wire it on the `<form>`:
 *
 *   data-controller="ui--appearance" data-action="change->ui--appearance#apply"
 *
 * The field names it reads — `appearance[colorMode]`, `appearance[accent]`… — are those of
 * `AppearanceType`. No user-facing string here: it only toggles DOM attributes.
 */
export default class extends Controller {
    connect() {
        this.apply();
    }

    apply() {
        const root = document.documentElement;

        const radio = (name) => {
            const el = this.element.querySelector(`[name="appearance[${name}]"]:checked`);
            return el ? el.value : null;
        };
        const toggle = (name) => {
            const el = this.element.querySelector(`[name="appearance[${name}]"]`);
            return el ? el.checked : false;
        };

        const mode = radio('colorMode') || 'light';
        this._applyMode(mode, root);

        const accent = radio('accent');
        if (accent) root.setAttribute('data-accent', accent);

        const density = radio('density');
        if (density) root.setAttribute('data-density', density);

        const font = radio('fontScale');
        if (font) root.setAttribute('data-font', font);

        root.toggleAttribute('data-contrast', toggle('highContrast'));
        if (toggle('highContrast')) root.setAttribute('data-contrast', 'high');

        root.toggleAttribute('data-motion', toggle('reducedMotion'));
        if (toggle('reducedMotion')) root.setAttribute('data-motion', 'reduced');

        // Keep the first-paint script (which reads localStorage 'theme') in sync.
        localStorage.setItem('theme', mode === 'dark' ? 'dark' : 'light');
    }

    _applyMode(mode, root) {
        let dark = mode === 'dark';
        if (mode === 'system') {
            dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        }
        root.classList.toggle('dark', dark);
    }
}
