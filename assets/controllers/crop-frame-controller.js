import { Controller } from '@hotwired/stimulus';
import { MAX_ZOOM, place, zoomRange } from '../demos/image/crop-demo.js';

/*
 * Upload, Crop & Serve: the square of the cropper stays still, the photo moves under it.
 *
 * Cropper.js keeps the photo around the square (viewMode 1). This controller
 * gives the square its size, the zoom its slider, and the photo its keys:
 * the arrows move it, + and - zoom.
 */

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['zoom', 'slider'];

    #cropper = null;

    // Both controllers load lazily: the cropper may be there first
    connect() {
        const img = this.element.querySelector('img.cropperjs-image');
        if (img?.cropper) {
            this.#take(img);
        }
    }

    // On the "cropperjs:connect" event
    attach({ detail: { img } }) {
        this.#take(img);
    }

    // On the "input" event of the slider: how many times closer than the photo that just covers the square
    zoom() {
        this.#zoomTo(Number(this.sliderTarget.value));
    }

    zoomIn() {
        this.#zoomTo(Number(this.sliderTarget.value) * 1.25);
    }

    zoomOut() {
        this.#zoomTo(Number(this.sliderTarget.value) / 1.25);
    }

    #take(img) {
        if (this.#cropper || !img.cropper) {
            return;
        }

        this.#cropper = img.cropper;
        img.addEventListener('crop', () => this.#show());
        img.addEventListener('zoom', (event) => this.#limit(event));
        img.addEventListener('cropstart', () => this.element.classList.add('is-moving'));
        img.addEventListener('cropend', () => this.element.classList.remove('is-moving'));

        if (this.#cropper.ready) {
            this.#frame();
        } else {
            img.addEventListener('ready', () => this.#frame(), { once: true });
        }
    }

    /*
     * Cropper.js starts from the selection of the form. The square takes its
     * place at the center of the stage, and the photo goes where the square
     * shows that same selection.
     */
    #frame() {
        const cropper = this.#cropper;
        const stage = cropper.getContainerData();
        const { naturalWidth: width, naturalHeight: height } = cropper.getCanvasData();
        const { cropBox, canvas } = place(stage, { width, height }, cropper.getData());

        // A dot at the center first: Cropper.js keeps the photo around the square, whatever its size
        cropper.setCropBoxData({ left: stage.width / 2 - 1, top: stage.height / 2 - 1, width: 2, height: 2 });
        cropper.setCanvasData(canvas);
        cropper.setCropBoxData(cropBox);

        this.sliderTarget.min = 1;
        this.sliderTarget.max = MAX_ZOOM;
        this.zoomTarget.hidden = false;

        const photo = this.element.querySelector('.cropperjs');
        photo.tabIndex = 0;
        photo.setAttribute('role', 'group');
        photo.setAttribute('aria-label', 'Photo: the arrow keys move it, the + and - keys zoom');
        photo.addEventListener('keydown', (event) => this.#key(event));

        this.#show();
    }

    #range() {
        return zoomRange(this.#cropper.getCropBoxData().width, this.#size());
    }

    #size() {
        const { naturalWidth: width, naturalHeight: height } = this.#cropper.getCanvasData();

        return { width, height };
    }

    // The slider follows the wheel, the pinch and the keys
    #show() {
        const { width, naturalWidth } = this.#cropper.getCanvasData();
        this.sliderTarget.value = width / naturalWidth / this.#range().min;
    }

    #zoomTo(factor) {
        const { left, top, width } = this.#cropper.getCropBoxData();
        const { min, max } = this.#range();

        // Around the center of the square: what it shows stays in it
        this.#cropper.zoomTo(Math.min(Math.max(min * factor, min), max), { x: left + width / 2, y: top + width / 2 });
    }

    // The wheel and the pinch go no further than the slider
    #limit(event) {
        const { ratio, oldRatio } = event.detail;
        const { min, max } = this.#range();
        const bound = Math.min(Math.max(ratio, min), max);
        if (Math.abs(bound - ratio) < 1e-9) {
            return;
        }

        event.preventDefault();
        if (Math.abs(bound - oldRatio) > 1e-9) {
            this.#zoomTo(bound / min);
        }
    }

    #key(event) {
        const step = event.shiftKey ? 40 : 8;
        const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };

        if (moves[event.key]) {
            this.#cropper.move(...moves[event.key]);
        } else if ('+' === event.key || '=' === event.key) {
            this.zoomIn();
        } else if ('-' === event.key) {
            this.zoomOut();
        } else {
            return;
        }

        event.preventDefault();
    }
}
