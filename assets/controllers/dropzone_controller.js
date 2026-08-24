import { Controller } from "@hotwired/stimulus";

/**
 * Dropzone — wraps a native `<input type="file">` in a drag-and-drop surface with a preview
 * thumbnail and the file's name and size.
 *
 * ⚠️ This controller ships WITH the `.dropzone-*` styles this bundle already publishes
 * (`assets/styles/components.css`). Shipping the vocabulary without the behaviour is how a drop
 * zone ends up drawn but inert: the markup renders, the click does nothing, and nothing throws.
 * That exact split — styles here, controller living in one consuming project — is what this file
 * closes (2026-08-24).
 *
 * The native input is KEPT and merely covered (`.dropzone-input` is `absolute inset-0 opacity-0`),
 * so Symfony's constraints, `required` and the file picker all keep working without JavaScript.
 *
 * Size units are a VALUE, not a literal: a bundle cannot know the language of the application
 * embedding it. The default is the SI symbol set; pass `unitsValue` to translate it.
 */
export default class extends Controller {
    static targets = ["input", "body", "preview", "previewImage", "name", "size", "primary"];

    static values = {
        inputId: String,
        browseLabel: { type: String, default: "" },
        dropLabel: { type: String, default: "" },
        selectedLabel: { type: String, default: "" },
        units: { type: Array, default: ["B", "KB", "MB", "GB"] },
    };

    connect() {
        this._onDragOver = this._onDragOver.bind(this);
        this._onDragLeave = this._onDragLeave.bind(this);
        this._onDrop = this._onDrop.bind(this);

        this.element.addEventListener("dragover", this._onDragOver);
        this.element.addEventListener("dragleave", this._onDragLeave);
        this.element.addEventListener("drop", this._onDrop);
    }

    disconnect() {
        this.element.removeEventListener("dragover", this._onDragOver);
        this.element.removeEventListener("dragleave", this._onDragLeave);
        this.element.removeEventListener("drop", this._onDrop);
    }

    onChange() {
        const file = this.hasInputTarget ? this.inputTarget.files?.[0] || null : null;

        if (file) {
            this._showPreview(file);
        } else {
            this._hidePreview();
        }
    }

    clear(event) {
        event?.preventDefault();
        event?.stopPropagation();

        if (this.hasInputTarget) {
            this.inputTarget.value = "";
        }

        this._hidePreview();
    }

    _onDragOver(event) {
        event.preventDefault();
        event.stopPropagation();
        this.element.classList.add("is-dragging");
    }

    _onDragLeave(event) {
        if (event.target !== this.element) return;
        this.element.classList.remove("is-dragging");
    }

    _onDrop(event) {
        event.preventDefault();
        event.stopPropagation();
        this.element.classList.remove("is-dragging");

        const files = event.dataTransfer?.files;
        if (!files || files.length === 0 || !this.hasInputTarget) return;

        // Hand the files to the native input rather than keeping them aside: that is what makes
        // Symfony's validation and the normal submit path see them.
        this.inputTarget.files = files;
        this.inputTarget.dispatchEvent(new Event("change", { bubbles: true }));
    }

    _showPreview(file) {
        if (this.hasBodyTarget) this.bodyTarget.classList.add("hidden");
        if (this.hasPreviewTarget) this.previewTarget.classList.remove("hidden");
        if (this.hasNameTarget) this.nameTarget.textContent = file.name;
        if (this.hasSizeTarget) this.sizeTarget.textContent = this._formatSize(file.size);

        if (!this.hasPreviewImageTarget) return;

        if (file.type && file.type.startsWith("image/")) {
            const reader = new FileReader();
            reader.onload = () => {
                this.previewImageTarget.src = reader.result;
                this.previewImageTarget.classList.remove("hidden");
            };
            reader.readAsDataURL(file);
        } else {
            this.previewImageTarget.classList.add("hidden");
            this.previewImageTarget.removeAttribute("src");
        }
    }

    _hidePreview() {
        if (this.hasBodyTarget) this.bodyTarget.classList.remove("hidden");
        if (this.hasPreviewTarget) this.previewTarget.classList.add("hidden");
        if (this.hasPreviewImageTarget) {
            this.previewImageTarget.classList.add("hidden");
            this.previewImageTarget.removeAttribute("src");
        }
    }

    _formatSize(bytes) {
        if (!Number.isFinite(bytes)) return "";

        const units = this.unitsValue.length > 0 ? this.unitsValue : ["B", "KB", "MB", "GB"];
        let size = bytes;
        let unit = 0;

        while (size >= 1024 && unit < units.length - 1) {
            size /= 1024;
            unit++;
        }

        return `${size.toFixed(size >= 10 || unit === 0 ? 0 : 1)} ${units[unit]}`;
    }
}
