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

        const inputId = `${this.element.id || 'searchable-select'}-input-${Math.random().toString(36).slice(2)}`;
        const listId = `${inputId}-list`;

        const input = document.createElement('input');
        input.type = 'search';
        input.autocomplete = 'off';
        input.placeholder = this.placeholderValue || 'Search user';
        input.classList.add('searchable-select__input');
        input.style.width = '100%';
        input.setAttribute('list', listId);
        input.id = inputId;

        const list = document.createElement('datalist');
        list.id = listId;

        Array.from(this.element.options).forEach((option) => {
            if (!option.value) {
                return;
            }
            const item = document.createElement('option');
            item.value = option.textContent.trim();
            item.dataset.value = option.value;
            list.appendChild(item);
        });

        const selectedOption = this.element.selectedOptions[0];
        if (selectedOption && selectedOption.value) {
            input.value = selectedOption.textContent.trim();
        }

        this.element.style.display = 'none';
        this.element.parentNode.insertBefore(input, this.element);
        this.element.parentNode.insertBefore(list, this.element);

        input.addEventListener('input', () => {
            const query = input.value.trim();
            const option = Array.from(this.element.options).find(
                (opt) => opt.textContent.trim() === query
            );

            if (option) {
                this.element.value = option.value;
            } else {
                this.element.value = '';
            }
        });
    }
}