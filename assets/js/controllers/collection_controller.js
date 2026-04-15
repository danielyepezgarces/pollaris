// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

import { FOCUSABLE_ELEMENTS } from '../query_selectors.js';

export default class extends Controller {
    static targets = ['container', 'prototype']

    static values = {
        index: Number,
        removeLabel: String,
    }

    connect () {
        this.refreshLabels();
    }

    addElement () {
        const element = this.prototypeTarget.content.firstElementChild.cloneNode(true);
        element.innerHTML = element.innerHTML.replace(/__name__/g, this.indexValue);

        this.containerTarget.appendChild(element);

        this.indexValue++;

        this.refreshLabels();

        const focusableElements = Array.from(element.querySelectorAll(FOCUSABLE_ELEMENTS));
        if (focusableElements.length >= 1) {
            focusableElements[0].focus();
        }
    }

    addValue (value) {
        const elements = Array.from(this.containerTarget.querySelectorAll('[data-item="element"]'));

        let element = elements.find((element) => {
            const input = element.querySelector('input');

            if (!input) {
                return false;
            }

            return input.value === '';
        });

        if (!element) {
            this.addElement();

            element = this.containerTarget.lastChild;
        }

        if (!element) {
            return;
        }

        const input = element.querySelector('input');

        if (!input) {
            return;
        }

        input.value = value;
    }

    ensureAtLeastOneElement () {
        const elements = this.containerTarget.querySelectorAll('[data-item="element"]');

        if (elements.length === 0) {
            this.addElement();
        }
    }

    removeElement (event) {
        const target = event.target;
        const element = target.closest('[data-item="element"]');

        element.remove();

        this.refreshLabels();
    }

    removeByValue (value) {
        const elements = this.containerTarget.querySelectorAll('[data-item="element"]');

        elements.forEach((element) => {
            const input = element.querySelector('input');

            if (!input) {
                return;
            }

            if (input.value !== value) {
                return;
            }

            element.remove();
        });

        this.refreshLabels();
    }

    refreshLabels () {
        const labels = this.containerTarget.querySelectorAll('label');
        labels.forEach((label, index) => {
            // Update the labels with the correct number.
            let labelPattern = label.dataset.labelPattern;

            if (!labelPattern) {
                // First time we refresh the labels, we save the content of
                // labels as patterns.
                labelPattern = label.innerHTML;
                label.dataset.labelPattern = labelPattern;
            }

            label.innerHTML = labelPattern.replace(/__number__/, index + 1);
        });
    }
}
