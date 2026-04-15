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
