import { Controller } from '@hotwired/stimulus';

/**
 * French number formatting for a decimal input — prices, quantities, rates.
 *
 * ⚠️ **The ui-bundle's two decimal types (`CustomMoneyType`, `CustomUnitType`) attach
 * `form--decimal` to their input and ship no controller**: exposing Stimulus controllers from a
 * form bundle would mean choosing AssetMapper or Encore for every consumer. The controller lives
 * here, where the ecosystem's other form controllers already live, and each project re-exports it
 * under `assets/controllers/form/decimal_controller.js` so its identifier is `form--decimal`.
 * Without it, every one of those fields is a plain input showing "89.00" — which works, and is
 * not what the type promises.
 *
 * It formats INDEPENDENTLY of the ambient Intl locale, like core-bundle's `format_number` does on
 * the server: the back-office may run in English and still bill in euros.
 *
 * - connect / blur → formatted value ("3 600,00", `decimals` places);
 * - focus          → plain editable value ("3600.00": dot, no grouping), fully selected, so the
 *                    first keystroke replaces it and any live-total controller that
 *                    `parseFloat()`s the raw value keeps working;
 * - form submit    → plain value again, so the server parses it whatever its own locale.
 *
 * ⚠️ **A `type="number"` input cannot hold "3 600,00"**: the browser silently empties it. A field
 * rendered with `html5: true` is therefore switched to `type="text"` + `inputmode="decimal"` —
 * same mobile keypad, and a value the controller can format. The submitted value is unchanged
 * (plain dot notation), which is what an `html5` NumberType expects.
 *
 * The static `parse()` is public API: other controllers read possibly-formatted values with it
 * (line totals, VAT totals).
 */
export default class extends Controller {
    static values = { decimals: { type: Number, default: 2 } };

    /** Locale-tolerant parse: "3 600,00", "3,600.00", "3600.00" → 3600; nothing numeric → null. */
    static parse(str) {
        if (str === null || str === undefined) {
            return null;
        }
        let s = String(str).trim();
        if (s === '') {
            return null;
        }
        s = s.replace(/[\s  ]/g, '');
        const lastComma = s.lastIndexOf(',');
        const lastDot = s.lastIndexOf('.');
        if (lastComma > -1 && lastDot > -1) {
            const decimalSeparator = lastComma > lastDot ? ',' : '.';
            const thousandsSeparator = decimalSeparator === ',' ? '.' : ',';
            s = s.split(thousandsSeparator).join('').replace(decimalSeparator, '.');
        } else if (lastComma > -1) {
            s = s.replace(',', '.');
        }
        const n = Number.parseFloat(s);

        return Number.isNaN(n) ? null : n;
    }

    connect() {
        if (this.element.type === 'number') {
            this.element.type = 'text';
            this.element.inputMode = 'decimal';
        }

        this._onFocus = this._onFocus.bind(this);
        this._onBlur = this._onBlur.bind(this);
        this._onSubmit = this._onSubmit.bind(this);

        this.element.addEventListener('focus', this._onFocus);
        this.element.addEventListener('blur', this._onBlur);

        this._form = this.element.form;
        this._form?.addEventListener('submit', this._onSubmit);

        this._format();
    }

    disconnect() {
        this.element.removeEventListener('focus', this._onFocus);
        this.element.removeEventListener('blur', this._onBlur);
        this._form?.removeEventListener('submit', this._onSubmit);
    }

    _format() {
        const n = this.constructor.parse(this.element.value);
        if (n === null) {
            return;
        }
        this.element.value = n.toLocaleString('fr-FR', {
            minimumFractionDigits: this.decimalsValue,
            maximumFractionDigits: this.decimalsValue,
        });
    }

    /** The machine value: dot decimal, no grouping. */
    _plain() {
        const n = this.constructor.parse(this.element.value);
        if (n === null) {
            return;
        }
        this.element.value = String(n);
    }

    _onFocus() {
        this._plain();
        // Deferred: some browsers clear the selection right after a focus-by-click.
        requestAnimationFrame(() => {
            if (document.activeElement === this.element) {
                this.element.select();
            }
        });
    }

    _onBlur() {
        this._format();
    }

    _onSubmit() {
        this._plain();
    }
}
