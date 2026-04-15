// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['select'];
    }

    connect () {
        this.updateColorScheme();
    }

    change (event) {
        localStorage.setItem('color-scheme', this.selectTarget.value);
        this.updateColorScheme();
    }

    selectTargetConnected () {
        const colorScheme = this.getColorSchemeFromLocalStorage();
        this.selectTarget.value = colorScheme;
    }

    updateColorScheme () {
        const colorScheme = this.getColorSchemeFromLocalStorage();

        if (colorScheme === 'light' || colorScheme === 'dark') {
            document.documentElement.dataset.colorScheme = colorScheme;
            return;
        }

        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            if (mediaQuery.matches) {
                document.documentElement.dataset.colorScheme = 'dark';
            } else {
                document.documentElement.dataset.colorScheme = 'light';
            }

            mediaQuery.addEventListener('change', (event) => {
                document.documentElement.dataset.colorScheme = event.matches ? 'dark' : 'light';
            });
        } else {
            document.documentElement.dataset.colorScheme = 'light';
        }
    }

    getColorSchemeFromLocalStorage () {
        const colorScheme = localStorage.getItem('color-scheme');

        if (
            colorScheme === 'auto' ||
            colorScheme === 'light' ||
            colorScheme === 'dark'
        ) {
            return colorScheme;
        }

        return 'auto';
    }
}
