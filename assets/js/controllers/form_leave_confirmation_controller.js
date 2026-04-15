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
    static get targets () {
        return ['input'];
    }

    static values = {
        confirmation: String,
    }

    connect() {
        this.shouldCheck = true;
    }

    check(event) {
        if (this.shouldCheck && this.anyChangedValue()) {
            if (event.type === 'turbo:before-visit') {
                if (!window.confirm(this.confirmationValue)) {
                    event.preventDefault()
                }
            } else {
                event.returnValue = this.confirmationValue;
                return this.confirmationValue;
            }
        }
    }

    disableCheck(event) {
        // This is used to not ask for confirmation on form submission.
        this.shouldCheck = false;
    }

    anyChangedValue() {
        return this.inputTargets.some((input) => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.defaultChecked !== input.checked;
            } else {
                return input.defaultValue !== input.value;
            }
        });
    }
}
