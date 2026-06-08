// This file is part of Pollaris.
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
    static targets = ['element']

    apply (event) {
        event.preventDefault();

        // Load all the slots collections in dates
        const dateCollections = document.querySelectorAll('[data-item="date-collection"]');
        if (dateCollections.length < 2) {
            return;
        }

        // Get the first date collection (Day 1)
        const firstCollection = dateCollections[0];
        const firstElements = firstCollection.querySelectorAll('[data-item="element"]');
        
        // Extract start and end times from Day 1
        const sourceValues = Array.from(firstElements).map(el => {
            const inputs = el.querySelectorAll('input[type="time"]');
            if (inputs.length < 2) return null;
            return {
                start: inputs[0].value,
                end: inputs[1].value
            };
        }).filter(v => v !== null && (v.start !== '' || v.end !== ''));

        if (sourceValues.length === 0) {
            return; // Nothing to copy
        }

        // Apply to all other collections
        for (let i = 1; i < dateCollections.length; i++) {
            const targetCollection = dateCollections[i];
            const collectionController = this.application.getControllerForElementAndIdentifier(targetCollection, 'collection');

            // Find all existing empty elements to potentially reuse
            const existingElements = targetCollection.querySelectorAll('[data-item="element"]');
            const emptyElements = Array.from(existingElements).filter(el => {
                const inputs = el.querySelectorAll('input[type="time"]');
                if (inputs.length < 2) return false;
                return inputs[0].value === '' && inputs[1].value === '';
            });

            sourceValues.forEach(sourceValue => {
                // Check if this exact slot already exists
                const currentElements = targetCollection.querySelectorAll('[data-item="element"]');
                const exists = Array.from(currentElements).some(el => {
                    const inputs = el.querySelectorAll('input[type="time"]');
                    if (inputs.length < 2) return false;
                    return inputs[0].value === sourceValue.start && inputs[1].value === sourceValue.end;
                });

                if (exists) {
                    return;
                }

                // Try to reuse an empty element if available
                let elementToFill = null;
                if (emptyElements.length > 0) {
                    elementToFill = emptyElements.shift();
                } else {
                    // Add element
                    collectionController.addElement();
                    const newElements = targetCollection.querySelectorAll('[data-item="element"]');
                    if (newElements.length > 0) {
                        elementToFill = newElements[newElements.length - 1];
                    }
                }

                if (elementToFill) {
                    const newInputs = elementToFill.querySelectorAll('input[type="time"]');
                    if (newInputs.length >= 2) {
                        newInputs[0].value = sourceValue.start;
                        newInputs[1].value = sourceValue.end;
                    }
                }
            });
        }
    }
}
