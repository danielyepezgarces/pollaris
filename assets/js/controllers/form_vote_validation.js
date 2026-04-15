// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['input', 'submitButton', 'voteHelp'];
    }

    static values = {
        requiredChoice: String,
    }

    connect() {
        this.updateUI();
    }

    updateUI() {
        if (!this.anyCheckedValue()) {
            this.voteHelpTarget.removeAttribute('hidden');
            this.voteHelpTarget.removeAttribute('aria-hidden');
            this.submitButtonTarget.disabled = true;
            this.submitButtonTarget.setAttribute('aria-describedby', this.voteHelpTarget.id);
        } else {
            this.voteHelpTarget.setAttribute('hidden', 'hidden');
            this.voteHelpTarget.setAttribute('aria-hidden', 'true');
            this.submitButtonTarget.disabled = false;
            this.submitButtonTarget.removeAttribute('aria-describedby');
        }
    }

    onSubmit(e) {
        if (!this.anyCheckedValue()) {
            e.preventDefault();
            window.confirm(this.requiredChoiceValue);
        }
    }

    onChange() {
        this.updateUI();
    }

    anyCheckedValue() {
        return this.inputTargets.some((input) => {
            if (input.type === 'checkbox' || input.type === 'radio') {
                return input.checked;
            }
        });
    }
}
