// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['isPasswordProtected', 'firstPlainPassword', 'secondPlainPassword', 'isPasswordForVotesOnly'];
    }

    connect() {
        this.refresh();
    }

    refresh() {
        if (this.isPasswordProtectedTarget.checked) {
            this.firstPlainPasswordTarget.disabled = false;
            this.secondPlainPasswordTarget.disabled = false;
            this.isPasswordForVotesOnlyTarget.disabled = false;
        } else {
            this.firstPlainPasswordTarget.value = '';
            this.firstPlainPasswordTarget.disabled = true;
            this.secondPlainPasswordTarget.value = '';
            this.secondPlainPasswordTarget.disabled = true;
            this.isPasswordForVotesOnlyTarget.checked = false;
            this.isPasswordForVotesOnlyTarget.disabled = true;
        }
    }
}
