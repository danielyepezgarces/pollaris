// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    print() {
        const previousColorScheme = document.documentElement.dataset.colorScheme;

        // Always print in "light" mode
        document.documentElement.dataset.colorScheme = 'light';
        print();
        document.documentElement.dataset.colorScheme = previousColorScheme;
    }
}
