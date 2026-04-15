// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['element']

    apply (event) {
        event.preventDefault();

        // Load all the slots collections in dates
        const dateCollections = document.querySelectorAll('[data-item="date-collection"]');
        const slotsInputsSelector = '[data-item="element"] input[type="text"]';

        dateCollections.forEach((dateCollection) => {
            // Load the Stimulus "collection" controller of this element
            const collectionController = this.application.getControllerForElementAndIdentifier(dateCollection, 'collection');

            // Then, iterate over the different element to add to the different
            // collections.
            this.elementTargets.forEach((element) => {
                if (!element.value) {
                    return;
                }

                // Load the existing inputs and check that the value doesn't
                // already exist.
                let slotsInputs = dateCollection.querySelectorAll(slotsInputsSelector);

                const valueExists = Array.from(slotsInputs).some((input) => {
                    return input.value === element.value;
                });

                if (valueExists) {
                    return;
                }

                // Then, add a new element and set its value to the element
                // value.
                collectionController.addElement();

                slotsInputs = dateCollection.querySelectorAll(slotsInputsSelector);

                if (slotsInputs.length === 0) {
                    // There is no input, but it should never happen since we
                    // added an element just above.
                    return;
                }

                const lastInput = slotsInputs[slotsInputs.length - 1];
                lastInput.value = element.value;
            });
        });
    }
}
