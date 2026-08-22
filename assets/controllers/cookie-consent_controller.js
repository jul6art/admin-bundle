import { Controller } from '@hotwired/stimulus';

/**
 * Cookie Consent Controller — GDPR-compliant cookie banner.
 *
 * Stores consent in a cookie for a configurable duration.
 * Dispatches a `cookie:consent` DOM event when accepted/declined.
 *
 * Usage in Twig:
 *   <div data-controller="ui--cookie-consent"
 *        data-ui--cookie-consent-duration-value="365"
 *        data-ui--cookie-consent-cookie-name-value="cookie_consent">
 *       ...
 *   </div>
 */
export default class extends Controller {
    static values = {
        duration: { type: Number, default: 365 },
        cookieName: { type: String, default: 'cookie_consent' },
    };

    connect() {
        if (this._hasConsent()) {
            this.element.remove();
            return;
        }
        this.element.classList.add('cookie-consent--visible');
    }

    accept() {
        this._setConsent('accepted');
        this._dismiss();
        document.dispatchEvent(new CustomEvent('cookie:consent', { detail: { status: 'accepted' } }));
    }

    decline() {
        this._setConsent('declined');
        this._dismiss();
        document.dispatchEvent(new CustomEvent('cookie:consent', { detail: { status: 'declined' } }));
    }

    _hasConsent() {
        return document.cookie.split(';').some(c => c.trim().startsWith(this.cookieNameValue + '='));
    }

    _setConsent(value) {
        const expires = new Date();
        expires.setDate(expires.getDate() + this.durationValue);
        document.cookie = `${this.cookieNameValue}=${value}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
    }

    _dismiss() {
        this.element.classList.remove('cookie-consent--visible');
        this.element.classList.add('cookie-consent--hidden');
        setTimeout(() => this.element.remove(), 300);
    }
}
