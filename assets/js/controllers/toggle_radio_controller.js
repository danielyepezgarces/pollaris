// This file is part of Pollaris.
// Copyright 2024-2025 Adrien Sch
// SPDX-License-Identifier: AGPL-3.0-or-later

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
