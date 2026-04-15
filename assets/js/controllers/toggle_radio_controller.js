// This file is part of Pollaris.
// Copyright 2024-2025 Adrien Sch
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
    selected = null;

    connect() {
        this.selected = this.element.querySelector('input[type="radio"]:checked');
    }

    toggle(event) {
        const radio = event.target;

        if (radio.type !== 'radio') return;

        if (this.selected === radio) {
            radio.checked = false;
            radio.dispatchEvent(
                new Event('change', { bubbles: true })
            );
            this.selected = null;
        } else {
            this.selected = radio;
        }
    }
}
