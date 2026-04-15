// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';
import dayjs from 'dayjs';
import dayjsPluginWeekOfYear from 'dayjs/plugin/weekOfYear';

import { setDayjsLocale } from '../dayjs_locale.js';

dayjs.extend(dayjsPluginWeekOfYear);

export default class extends Controller {
    static targets = [
        'previousMonth',
        'currentMonth',
        'nextMonth',
        'days',
        'daysLabels',
        'source',
    ]

    connect () {
        setDayjsLocale(dayjs);

        this.periodSelectionStart = null;
        const firstSelectedDate = this.getFirstSelectedDate();
        if (firstSelectedDate) {
            this.focusedDate = firstSelectedDate;
        } else {
            this.focusedDate = dayjs();
        }

        this.refresh();
    }

    refresh () {
        this.daysLabelsTarget.innerHTML = '';
        this.daysTarget.innerHTML = '';

        this.refreshMonthHeader();
        this.refreshDaysLabels();
        this.refreshDays();
    }

    refreshMonthHeader () {
        this.currentMonthTarget.innerText = this.focusedDate.format('MMMM YYYY');

        const previousMonth = this.focusedDate.subtract(1, 'month').format('MMMM YYYY');
        const previousLabelPattern = this.previousMonthTarget.dataset.labelPattern;
        this.previousMonthTarget.ariaLabel = previousLabelPattern.replace(/__date__/, previousMonth);

        const nextMonth = this.focusedDate.add(1, 'month').format('MMMM YYYY');
        const nextLabelPattern = this.nextMonthTarget.dataset.labelPattern;
        this.nextMonthTarget.ariaLabel = nextLabelPattern.replace(/__date__/, nextMonth);
    }

    refreshDaysLabels () {
        const weekNode = document.createElement('tr');

        let dateIndex = this.focusedDate.startOf('week');

        for (let dayIndex = 0 ; dayIndex < 7 ; dayIndex++) {
            const th = document.createElement('th');

            th.scope = 'col';
            th.abbr = dateIndex.format('dddd');
            th.innerText = dateIndex.format('dd');

            weekNode.appendChild(th);

            dateIndex = dateIndex.add(1, 'day');
        }

        this.daysLabelsTarget.appendChild(weekNode);
    }

    refreshDays () {
        const firstDateOfMonth = this.focusedDate.startOf('month');
        const lastDateOfMonth = this.focusedDate.endOf('month');

        let firstWeekOfMonth = firstDateOfMonth.week();
        if (firstWeekOfMonth >= 52) {
            firstWeekOfMonth = 0;
        }

        let lastWeekOfMonth = lastDateOfMonth.week();
        if (lastWeekOfMonth === 1) {
            lastWeekOfMonth = 53;
        }

        let dateIndex = firstDateOfMonth.startOf('week');

        for (let weekIndex = firstWeekOfMonth ; weekIndex <= lastWeekOfMonth ; weekIndex++) {
            const weekNode = document.createElement('tr');

            for (let dayIndex = 0 ; dayIndex < 7 ; dayIndex++) {
                const td = document.createElement('td');
                const button = document.createElement('button');

                button.type = 'button';
                button.classList.add('calendar__day');
                if (!dateIndex.isSame(this.focusedDate, 'month')) {
                    button.classList.add('calendar__day--other-month');
                }
                button.tabIndex = dateIndex.isSame(this.focusedDate, 'day') ? 0 : -1;
                button.ariaLabel = dateIndex.format('LL');
                button.ariaSelected = this.isDateSelected(dateIndex);
                if (dateIndex.isSame(dayjs(), 'day')) {
                    button.ariaCurrent = 'date';
                }
                button.innerText = dateIndex.format('DD');
                button.dataset.action = 'calendar#switchSelection';
                button.dataset.value = dateIndex.format('YYYY-MM-DD');

                td.appendChild(button);
                weekNode.appendChild(td);

                dateIndex = dateIndex.add(1, 'day');
            }

            this.daysTarget.appendChild(weekNode);
        }
    }

    goToPreviousMonth () {
        this.focusedDate = this.focusedDate.subtract(1, 'month');
        this.refresh();
    }

    goToNextMonth () {
        this.focusedDate = this.focusedDate.add(1, 'month');
        this.refresh();
    }

    switchSelection (event) {
        const dateButton = event.target;
        const date = dayjs(dateButton.dataset.value);

        let dates = [date];
        let action = dateButton.ariaSelected === 'true' ? 'unselect' : 'select';
        if (event.shiftKey && this.periodSelectionStart) {
            dates = this.getDateValuesRange(this.periodSelectionStart, date, 'day');
            action = this.isDateSelected(this.periodSelectionStart) ? 'select' : 'unselect';
        }

        dates.forEach((date) => {
            if (
                (action === 'select' && this.isDateSelected(date)) ||
                (action === 'unselect' && !this.isDateSelected(date))
            ) {
                return;
            }

            const dateClicked = new CustomEvent('date-clicked', { detail: {
                value: date.format('YYYY-MM-DD'),
                action: action,
            } });
            this.element.dispatchEvent(dateClicked);
        });

        this.focusedDate = date;
        this.refresh();
        this.focusDateButton();
        this.periodSelectionStart = date;
    }

    getDateValuesRange (date1, date2, unit) {
        let currentDate = date1;
        let endDate = date2;
        if (date1.isAfter(date2)) {
            currentDate = date2;
            endDate = date1;
        }

        const range = [];
        while (currentDate.isBefore(endDate) || currentDate.isSame(endDate)) {
            range.push(currentDate.clone());
            currentDate = currentDate.add(1, unit);
        }

        return range;
    }

    isDateSelected (date) {
        const dateAsString = date.format('YYYY-MM-DD');

        return this.sourceTargets.some((input) => {
            return input.value === dateAsString;
        });
    }

    getFirstSelectedDate () {
        const dates = this.sourceTargets.map((input) => {
            return input.value;
        });

        dates.sort();

        if (dates.length > 0) {
            return dayjs(dates[0]);
        } else {
            return null;
        }
    }

    focusDateButton () {
        const currentDateValue = this.focusedDate.format('YYYY-MM-DD');
        const currentButton = this.daysTarget.querySelector(`button[data-value="${currentDateValue}"]`);
        if (currentButton) {
            currentButton.focus();
        }
    }

    handleKeydown (event) {
        const key = event.key;
        const shiftPressed = event.shiftKey;

        if (key === 'ArrowDown') {
            this.focusedDate = this.focusedDate.add(1, 'week');
        } else if (key === 'ArrowUp') {
            this.focusedDate = this.focusedDate.subtract(1, 'week');
        } else if (key === 'ArrowRight') {
            this.focusedDate = this.focusedDate.add(1, 'day');
        } else if (key === 'ArrowLeft') {
            this.focusedDate = this.focusedDate.subtract(1, 'day');
        } else if (key === 'PageDown' && shiftPressed) {
            this.focusedDate = this.focusedDate.add(1, 'year');
        } else if (key === 'PageUp' && shiftPressed) {
            this.focusedDate = this.focusedDate.subtract(1, 'year');
        } else if (key === 'PageDown') {
            this.focusedDate = this.focusedDate.add(1, 'month');
        } else if (key === 'PageUp') {
            this.focusedDate = this.focusedDate.subtract(1, 'month');
        } else if (key === 'Home') {
            this.focusedDate = this.focusedDate.startOf('week');
        } else if (key === 'End') {
            this.focusedDate = this.focusedDate.endOf('week');
        } else {
            return;
        }

        event.preventDefault();

        this.refresh();
        this.focusDateButton();
    }
}
