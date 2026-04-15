import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'pollaris.timezone-display-mode';

export default class extends Controller {
    static targets = ['input', 'option']

    connect() {
        const storedMode = window.localStorage.getItem(STORAGE_KEY);

        if (storedMode) {
            const input = this.inputTargets.find((candidate) => candidate.value === storedMode);

            if (input) {
                input.checked = true;
            }
        }

        this.syncActiveState();
        this.broadcast();
    }

    change() {
        window.localStorage.setItem(STORAGE_KEY, this.currentMode);
        this.syncActiveState();
        this.broadcast();
    }

    syncActiveState() {
        const currentMode = this.currentMode;

        this.optionTargets.forEach((option) => {
            const input = option.querySelector('input[type="radio"]');

            option.dataset.active = input?.value === currentMode ? 'true' : 'false';
        });
    }

    broadcast() {
        window.dispatchEvent(new CustomEvent('pollaris:timezone-display-change', {
            detail: {
                mode: this.currentMode,
            },
        }));
    }

    get currentMode() {
        const checkedInput = this.inputTargets.find((input) => input.checked);

        return checkedInput?.value ?? 'poll';
    }
}
