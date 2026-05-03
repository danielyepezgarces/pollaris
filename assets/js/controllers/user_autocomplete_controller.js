// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        url: String,
        minLength: { type: Number, default: 2 },
        placeholder: String,
    };

    connect() {
        if (this.element.dataset.userAutocompleteInitialized === 'true') {
            return;
        }

        if (this.element.tagName !== 'INPUT') {
            return;
        }

        this.element.dataset.userAutocompleteInitialized = 'true';

        const list = document.createElement('ul');
        list.classList.add('user-autocomplete__list');
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        this.element.parentNode.insertBefore(list, this.element.nextSibling);

        if (this.placeholderValue) {
            this.element.placeholder = this.placeholderValue;
        }

        let abortController = null;

        this.element.addEventListener('input', async () => {
            const query = this.element.value.trim();

            if (query.length < this.minLengthValue) {
                list.innerHTML = '';
                list.hidden = true;
                return;
            }

            if (abortController) {
                abortController.abort();
            }

            abortController = new AbortController();

            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', query);

            try {
                const response = await fetch(url.toString(), { signal: abortController.signal });
                if (!response.ok) {
                    return;
                }
                const results = await response.json();
                list.innerHTML = '';
                if (results.length === 0) {
                    list.hidden = true;
                    return;
                }

                results.forEach((item) => {
                    const option = document.createElement('li');
                    option.classList.add('user-autocomplete__item');
                    option.setAttribute('role', 'option');
                    option.dataset.username = item.username;
                    option.textContent = `${item.displayName} (@${item.username})`;
                    option.addEventListener('mousedown', () => {
                        this.element.value = item.username;
                        list.innerHTML = '';
                        list.hidden = true;
                    });
                    list.appendChild(option);
                });

                list.hidden = false;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                }
            }
        });

        this.element.addEventListener('blur', () => {
            setTimeout(() => {
                list.hidden = true;
            }, 150);
        });

        this.element.addEventListener('focus', () => {
            if (list.childElementCount > 0) {
                list.hidden = false;
            }
        });
    }
}
