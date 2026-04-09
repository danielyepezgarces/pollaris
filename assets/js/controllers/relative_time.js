// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (this.element.dataset.localized) return;

        const iso = this.element.getAttribute('datetime');

        if (!iso) return;

        const date = new Date(iso);
        const formatter = new Intl.DateTimeFormat(document.documentElement.lang, {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone
        });

        this.element.textContent = formatter.format(date);
        this.element.dataset.localized = 'true';
    }
}
