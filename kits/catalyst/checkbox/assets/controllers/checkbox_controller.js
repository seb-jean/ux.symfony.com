import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        indeterminate: Boolean,
    };

    indeterminateValueChanged() {
        this.element.indeterminate = this.indeterminateValue;
    }
}
