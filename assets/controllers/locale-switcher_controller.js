import { Controller } from '@hotwired/stimulus';

/**
 * Locale Switcher — posts the chosen locale to the switch endpoint via
 * fetch() then reloads the page. Kept JS-driven (not a `<form>`) so
 * tests that rely on `$crawler->filter('form')` stay deterministic: the
 * switcher does not introduce a competing form on every BO page.
 */
export default class extends Controller {
    static values = {
        url: String,
        token: String,
    };

    async switch(event) {
        event.preventDefault();
        const locale = event.currentTarget.dataset.locale;
        if (!locale) return;

        const body = new FormData();
        body.append('_token', this.tokenValue);
        body.append('locale', locale);

        try {
            await fetch(this.urlValue, {
                method: 'POST',
                body,
                credentials: 'same-origin',
            });
        } catch (e) {
            // Network failures are silent — page reload below still honors
            // the DB-persisted locale if the request actually reached the
            // server but the client dropped the response.
        }

        window.location.reload();
    }
}
