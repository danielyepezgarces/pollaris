// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

import * as Storage from '../storage.js';

export default class extends Controller {
    static get values () {
        return {
            namespace: String,
            key: String,
            entry: Object,
            init: Boolean,
        };
    }

    connect () {
        const namespace = this.namespaceValue;
        const key = this.keyValue;
        const hasEntry = Storage.getEntry(namespace, key) !== null;

        if (this.isElementCheckbox()) {
            this.element.checked = hasEntry;
        }

        if (this.shouldStore()) {
            // Make sure to synchronize the entry if it changed since the last visit.
            this.sync();
        }
    }

    sync () {
        if (this.shouldStore()) {
            Storage.storeEntry(this.namespaceValue, this.keyValue, this.entryValue);
        } else {
            Storage.unstoreEntry(this.namespaceValue, this.keyValue);
        }
    }

    isElementCheckbox () {
        return this.element.tagName === 'INPUT' && this.element.type === 'checkbox';
    }

    shouldStore () {
        if (this.isElementCheckbox()) {
            return this.element.checked;
        } else {
            return true;
        }
    }
};
