// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'switch',
        'calendar',
        'inputs',
    ]

    switch () {
        this.calendarTarget.hidden = !this.calendarTarget.hidden;
        this.inputsTarget.hidden = !this.inputsTarget.hidden;

        if (this.calendarTarget.hidden) {
            this.switchTarget.innerText = this.switchTarget.dataset.labelCalendar;
            this.ensureCollectionHasAtLeastOneElement();
        } else {
            this.switchTarget.innerText = this.switchTarget.dataset.labelManually;

            this.removeCollectionEmptyElements();
            this.refreshCalendar();
        }
    }

    switchDateSelection (event) {
        const collectionController = this.application.getControllerForElementAndIdentifier(this.element, 'collection');

        if (!collectionController) {
            return;
        }

        if (event.detail.action === 'select') {
            collectionController.addValue(event.detail.value);
        } else {
            collectionController.removeByValue(event.detail.value);
        }
    }

    refreshCalendar () {
        const calendarController = this.application.getControllerForElementAndIdentifier(this.element, 'calendar');

        if (!calendarController) {
            return;
        }

        calendarController.refresh();
    }

    ensureCollectionHasAtLeastOneElement () {
        const collectionController = this.application.getControllerForElementAndIdentifier(this.element, 'collection');

        if (!collectionController) {
            return;
        }

        collectionController.ensureAtLeastOneElement();
    }

    removeCollectionEmptyElements () {
        const collectionController = this.application.getControllerForElementAndIdentifier(this.element, 'collection');

        if (!collectionController) {
            return;
        }

        collectionController.removeByValue('');
    }
}
