import { Controller } from '@hotwired/stimulus';

/* stimulusFetch: 'lazy' */
export default class extends Controller {
    static targets = ['option', 'temporary', 'from', 'to'];

    static values = { decision: String, rangeMessage: String };

    connect() {
        this.decision = document.getElementById(this.decisionValue);
        this.decisionChanged = () => this.decisionToggled();
        this.decision?.addEventListener('change', this.decisionChanged);
        this.toggle();
    }

    disconnect() {
        this.decision?.removeEventListener('change', this.decisionChanged);
    }

    decisionToggled() {
        const enabled = !!this.decision?.checked;
        this.optionTargets.forEach((option) => {
            option.disabled = !enabled;
        });
        if (!enabled) {
            this.optionTargets.find((option) => option !== this.temporaryTarget).checked = true;
        }
        this.toggle();
    }

    toggle() {
        const temporary = !this.temporaryTarget.disabled && this.temporaryTarget.checked;
        [this.fromTarget, this.toTarget].forEach((input) => {
            input.disabled = !temporary;
        });
        this.validate();
    }

    validate() {
        const invalid = !this.toTarget.disabled && '' !== this.fromTarget.value && '' !== this.toTarget.value && this.fromTarget.value > this.toTarget.value;
        this.toTarget.setCustomValidity(invalid ? this.rangeMessageValue : '');
        this.toTarget.classList.toggle('is-invalid', invalid);
    }
}
