import { Controller } from '@hotwired/stimulus';
import { reorder } from '../demos/image/crop-demo.js';

/*
 * Upload, Crop & Serve: the grid of photos.
 *
 * Once the upload of a photo completes, its form is submitted: it only carries
 * a reference. A photo dropped on another one takes its place, and the new
 * order goes to the server. A file from the computer may land on any free place.
 */

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['order'];

    #dragged = null;
    #target = null;

    /*
     * On the "complete" event of the upload field. The field fills its reference
     * input before it dispatches the event: the form is ready on the next task.
     */
    submit(event) {
        const form = event.target.closest('form');
        setTimeout(() => form?.requestSubmit());
    }

    // On the "dragstart" event of a photo
    pick(event) {
        this.#dragged = event.currentTarget;
        event.dataTransfer.effectAllowed = 'move';
        // Firefox starts no drag without data
        event.dataTransfer.setData('text/plain', '');
        // The browser takes its picture of the photo first: then the photo fades
        requestAnimationFrame(() => this.#dragged?.classList.add('is-dragged'));
    }

    // On the "dragover" event of the grid
    over(event) {
        if (this.#dragged) {
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            const photo = event.target.closest('.CropDemo_photo');
            this.#aim(photo === this.#dragged ? null : photo);
        } else if (event.dataTransfer.types.includes('Files')) {
            // A file dropped anywhere else would replace the page: a full grid takes none
            event.preventDefault();
            event.dataTransfer.dropEffect = this.#input ? 'copy' : 'none';
            event.currentTarget.classList.toggle('is-receiving', null !== this.#input);
        }
    }

    // On the "dragleave" event of the grid
    leave(event) {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            event.currentTarget.classList.remove('is-receiving');
            this.#aim(null);
        }
    }

    // On the "drop" event of the grid
    drop(event) {
        event.currentTarget.classList.remove('is-receiving');

        if (this.#dragged) {
            event.preventDefault();
            this.#move(this.#dragged, this.#target);
        } else if (this.#input && !event.target.closest('.ux-upload__dropzone') && event.dataTransfer.files.length > 0) {
            // The field takes the drops on its own place: the others hand it the file
            event.preventDefault();
            const transfer = new DataTransfer();
            transfer.items.add(event.dataTransfer.files[0]);
            this.#input.files = transfer.files;
            this.#input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        this.#aim(null);
    }

    // On the "dragend" event of a photo, dropped or not
    release() {
        this.#dragged?.classList.remove('is-dragged');
        this.#dragged = null;
        this.#aim(null);
    }

    get #input() {
        return this.element.querySelector('.CropDemo_add input[type="file"]');
    }

    #aim(photo) {
        this.#target?.classList.remove('is-target');
        this.#target = photo;
        this.#target?.classList.add('is-target');
    }

    #move(dragged, target) {
        if (!target || target === dragged) {
            return;
        }

        const names = [...this.element.querySelectorAll('.CropDemo_photo')].map((photo) => photo.dataset.name);
        const order = reorder(names, dragged.dataset.name, target.dataset.name);

        // The grid takes the new order at once, the server answers with the same one
        if (names.indexOf(dragged.dataset.name) < names.indexOf(target.dataset.name)) {
            target.after(dragged);
        } else {
            target.before(dragged);
        }

        this.orderTarget.elements.order.value = order.join(',');
        this.orderTarget.requestSubmit();
    }
}
