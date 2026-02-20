import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['image', 'fallback'];

    connect() {
        if (!this.hasImageTarget) return;

        if (this.imageTarget.complete && this.imageTarget.naturalWidth === 0) {
            this.showFallback();
        }
    }

    showFallback() {
        this.imageTarget.hidden = true;
        if (this.hasFallbackTarget) this.fallbackTarget.hidden = false;
    }
}
