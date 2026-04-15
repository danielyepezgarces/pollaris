// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

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
