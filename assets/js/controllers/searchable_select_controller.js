// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        placeholder: String,
    };

    connect() {
        if (this.element.dataset.searchableSelectInitialized === 'true') {
            return;
        }

        if (this.element.tagName !== 'SELECT') {
            return;
        }

        this.element.dataset.searchableSelectInitialized = 'true';

        const input = document.createElement('input');
        input.type = 'search';
        input.autocomplete = 'off';
        input.placeholder = this.placeholderValue || 'Search user';
        input.classList.add('searchable-select__input');
        input.style.width = '100%';

        this.element.parentNode.insertBefore(input, this.element);

        input.addEventListener('input', () => {
            const query = input.value.trim().toLowerCase();

            Array.from(this.element.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                const text = option.textContent.trim().toLowerCase();
                option.hidden = query.length > 0 && !text.includes(query);
            });
        });
    }
}