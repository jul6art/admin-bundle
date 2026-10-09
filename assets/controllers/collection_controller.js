import { Controller } from "@hotwired/stimulus";

/**
 * Collection — adds and removes entries of a Symfony `CollectionType` in place.
 *
 * Symfony renders a `data-prototype`: the HTML of one empty row, with `__name__` where the index
 * belongs. This controller clones it, numbers it and inserts it. That is all an `allow_add`
 * collection needs on the browser side — and no bundle of this ecosystem shipped one, so every
 * project that edits a short collection was about to write its own.
 *
 * ⚠️ The index restarts from the CURRENT number of rows, never from a counter reset to zero. After
 * a removal followed by an addition, two rows would otherwise carry the same field name, and
 * Symfony would keep only one of them — silently, on submit.
 *
 * ⚠️ Removal deletes the NODE, with no "to be deleted" hidden field: the collection is expected to
 * be declared `allow_delete: true` and `by_reference: false`, so Symfony compares the submitted
 * collection to the stored one and calls the remover for what is missing. An `orphanRemoval: true`
 * on the association does the rest.
 *
 * ⚠️ The prototype and the existing rows MUST be rendered by the same template. A prototype that
 * drifts from the rows produces an added row with a different structure — different classes, a
 * different remove button — and this controller stops finding its targets in it.
 *
 * **Ordering (1.26)** — `up` and `down` move a row one place, for a short ordered collection (the
 * links of a footer, the steps of a checklist): two buttons per row, which a keyboard and a screen
 * reader reach like any button, where dragging would need a second path for them anyway.
 *
 * ⚠️ Moving a row does NOT reorder what Symfony receives: a field keeps its name, and its name
 * carries its index. The order travels in a hidden field of each row marked
 * `data-form--collection-target="position"`, renumbered here after every move, addition and
 * removal; the application sorts by it when it saves. A row without one moves on screen only.
 *
 * ```twig
 * <button type="button" data-action="form--collection#up">…</button>
 * <button type="button" data-action="form--collection#down">…</button>
 * {{ form_widget(row.position, {attr: {'data-form--collection-target': 'position'}}) }}
 * ```
 */
export default class extends Controller {
    static targets = ["container", "item", "position"];

    static values = {
        prototype: String,
        // Index of the next entry. Exposed as a value so a template can force it.
        index: Number,
    };

    connect() {
        if (!this.hasIndexValue) {
            this.indexValue = this.itemTargets.length;
        }
    }

    add(event) {
        event.preventDefault();

        const html = this.prototypeValue.replace(/__name__/g, String(this.indexValue));
        this.indexValue += 1;

        const wrapper = document.createElement("div");
        wrapper.innerHTML = html.trim();

        const item = wrapper.firstElementChild;
        if (!item) {
            return;
        }

        this.containerTarget.appendChild(item);
        this._renumber();

        // Focus the first field: whoever just clicked "Add" wants to type, not to hunt for where.
        item.querySelector("input, select, textarea")?.focus();
    }

    remove(event) {
        event.preventDefault();

        // `closest` on the item target rather than `parentElement`: the template's structure can
        // gain a level without breaking removal.
        event.currentTarget.closest('[data-form--collection-target="item"]')?.remove();
        this._renumber();
    }

    /** Moves the row one place up. */
    up(event) {
        this._move(event, -1);
    }

    /** Moves the row one place down. */
    down(event) {
        this._move(event, 1);
    }

    _move(event, step) {
        event.preventDefault();

        const button = event.currentTarget;
        const item = button.closest('[data-form--collection-target="item"]');
        const index = this.itemTargets.indexOf(item);
        const neighbour = this.itemTargets[index + step];

        if (index < 0 || !neighbour) {
            return;
        }

        if (step < 0) {
            neighbour.before(item);
        } else {
            neighbour.after(item);
        }

        this._renumber();

        // The keyboard stays where it was: on the button just pressed, which moved with its row.
        button.focus();
    }

    /** Every row's position field, in the order the rows now stand. */
    _renumber() {
        this.itemTargets.forEach((item, index) => {
            const position = item.querySelector('[data-form--collection-target="position"]');

            if (position) {
                position.value = String(index);
            }
        });
    }
}
