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

        const listId = `${this.element.id || 'user-autocomplete'}-list-${Math.random().toString(36).slice(2)}`;
        const list = document.createElement('datalist');
        list.id = listId;
        this.element.setAttribute('list', listId);
        this.element.parentNode.insertBefore(list, this.element.nextSibling);

        if (this.placeholderValue) {
            this.element.placeholder = this.placeholderValue;
        }

        let abortController = null;

        this.element.addEventListener('input', async () => {
            const query = this.element.value.trim();

            if (query.length < this.minLengthValue) {
                list.innerHTML = '';
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
                results.forEach((item) => {
                    const option = document.createElement('option');
                    option.value = item.username;
                    option.textContent = item.displayName;
                    list.appendChild(option);
                });
            } catch (error) {
                if (error.name !== 'AbortError') {
                    console.error(error);
                }
            }
        });
    }
}
