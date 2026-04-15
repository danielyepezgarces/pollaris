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
    }
}
