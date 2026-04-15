import { Controller } from '@hotwired/stimulus';
import dayjs from 'dayjs';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';

dayjs.extend(utc);
dayjs.extend(timezone);

export default class extends Controller {
    static values = {
        datetime: String,
        date: String,
        format: {
            type: String,
            default: 'HH:mm'
        },
        sourceTimezone: String,
        mode: {
            type: String,
            default: 'local'
        }
    }

    connect() {
        this.originalTextContent = this.element.textContent;
        this.handleModeChange = this.render.bind(this);
        window.addEventListener('pollaris:timezone-display-change', this.handleModeChange);
        this.render();
    }

    disconnect() {
        window.removeEventListener('pollaris:timezone-display-change', this.handleModeChange);
    }

    render(event = null) {
        if (!this.sourceTimezoneValue) return;

        const mode = event?.detail?.mode ?? window.localStorage.getItem('pollaris.timezone-display-mode') ?? this.modeValue;

        if (this.datetimeValue) {
            const sourceDate = dayjs.tz(this.datetimeValue, this.sourceTimezoneValue);
            const displayedDate = mode === 'local'
                ? sourceDate.tz(dayjs.tz.guess())
                : sourceDate;

            this.element.textContent = displayedDate.format(this.formatValue);

            return;
        }

        if (!this.dateValue) {
            return;
        }

        // Convert every HH:mm or HH:mm:ss occurrence so ranges are localized without seconds.
        const timePattern = /\b([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?\b/g;
        const localizedText = this.originalTextContent.replace(timePattern, (match, hours, minutes, seconds = '00') => {
            const sourceDateTime = dayjs.tz(`${this.dateValue}T${hours}:${minutes}:${seconds}`, this.sourceTimezoneValue);
            const displayedDateTime = mode === 'local'
                ? sourceDateTime.tz(dayjs.tz.guess())
                : sourceDateTime;

            return displayedDateTime.format(this.formatValue);
        });

        this.element.textContent = localizedText;
    }
}
