import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'dialog'];

    static values = {
        open: Boolean,
    };

    // Where the last press started: a drag from inside the dialog, released over the backdrop, is no click outside
    #pressedOutside = false;

    connect() {
        if (this.openValue) {
            this.open();
        }
    }

    dialogTargetConnected(dialog) {
        dialog.addEventListener('pointerdown', this.#rememberPress);
    }

    dialogTargetDisconnected(dialog) {
        dialog.removeEventListener('pointerdown', this.#rememberPress);
    }

    #rememberPress = (event) => {
        this.#pressedOutside = this.#isOutside(event);
    };

    #isOutside({ target, clientX, clientY }) {
        if (target !== this.dialogTarget) {
            return false;
        }

        // The dialog itself is the target of its padding as well as of its backdrop
        const { left, right, top, bottom } = this.dialogTarget.getBoundingClientRect();

        return clientX < left || clientX > right || clientY < top || clientY > bottom;
    }

    open() {
        this.dialogTarget.showModal();

        if (this.hasTriggerTarget) {
            if (this.dialogTarget.getAnimations().length > 0) {
                this.dialogTarget.addEventListener(
                    'transitionend',
                    () => {
                        this.triggerTarget.setAttribute('aria-expanded', 'true');
                    },
                    { once: true }
                );
            } else {
                this.triggerTarget.setAttribute('aria-expanded', 'true');
            }
        }
    }

    closeOnClickOutside(event) {
        if (this.#pressedOutside && this.#isOutside(event)) {
            this.close();
        }
    }

    close() {
        this.dialogTarget.close();

        if (this.hasTriggerTarget) {
            if (this.dialogTarget.getAnimations().length > 0) {
                this.dialogTarget.addEventListener('transitionend', () => {
                    this.triggerTarget.setAttribute('aria-expanded', 'false');
                });
            } else {
                this.triggerTarget.setAttribute('aria-expanded', 'false');
            }
        }
    }
}
