// This file is part of Pollaris.
// Copyright 2020-2022 Marien Fressinaud (Flus)
// Copyright 2022-2024 Probesys (Bileto)
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
    static get values () {
        return {
            selector: String,
        };
    }

    connect () {
        this.element.setAttribute('aria-haspopup', 'dialog');
        this.element.setAttribute('aria-controls', 'modal');
    }

    fetch (event) {
        event.preventDefault();
        this.dispatchOpenModalEvent('fetch');
    }

    copy (event) {
        event.preventDefault();
        this.dispatchOpenModalEvent('copy');
    }

    dispatchOpenModalEvent (mode) {
        const modal = document.getElementById('modal');

        const openModalEvent = new CustomEvent('open-modal', {
            detail: {
                target: this.element,
                mode: mode,
                selector: this.selectorValue,
            },
        });

        modal.dispatchEvent(openModalEvent);
    }
};
