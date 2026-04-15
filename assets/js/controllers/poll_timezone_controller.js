import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['browserTimezone']

    connect() {
        this.syncBrowserTimezone();
    }

    syncBrowserTimezone() {
        const browserTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';

        this.browserTimezoneTargets.forEach((input) => {
            input.value = browserTimezone;
        });

        if (browserTimezone) {
            const browserRadio = this.element.querySelector('input[type="radio"][value="browser"]');
            if (browserRadio) {
                const label = this.element.querySelector(`label[for="${browserRadio.id}"]`);
                if (label && !label.querySelector('.timezone-name')) {
                    const span = document.createElement('span');
                    span.className = 'timezone-name';
                    span.style.marginLeft = '0.25em';
                    span.textContent = `(${browserTimezone})`;
                    label.appendChild(span);
                }
            }
        }
    }
}
