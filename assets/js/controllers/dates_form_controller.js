// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

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
