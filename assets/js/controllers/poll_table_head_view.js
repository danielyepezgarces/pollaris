// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later
//
// Modified by Daniel Yepez Garces on 2026-04-15:
// - Migrated database backend from PostgreSQL to MariaDB for Toolforge deployment
// - Added Wikimedia login support
// - Removed local username/password authentication
// - Added multilingual survey support
// - Added user timezone display for survey times when different from server UTC

import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['thead', 'showMore', 'showLess', 'label'];

    isOpen = false;

    connect() {
        this.updateUI();

        // Complementary update when fonts are loaded
        document.fonts.ready.then(() => {
            this.updateUI();
        })
    }

    toggleSeeMore() {
        if (this.isOpen) {
            this.showLess();
        } else {
            this.showMore();
        }
    }

    showMore() {
        this.isOpen = true;
        this.theadTarget.setAttribute('data-show-more', this.isOpen);
        this.showLessTarget.setAttribute('aria-hidden', false);
        this.showMoreTarget.setAttribute('aria-hidden', true);
        this.showLessTarget.focus();
    }

    showLess() {
        this.isOpen = false;
        this.theadTarget.setAttribute('data-show-more', this.isOpen);
        this.showLessTarget.setAttribute('aria-hidden', true);
        this.showMoreTarget.setAttribute('aria-hidden', false);
        this.showMoreTarget.focus();
    }

    updateUI() {
        if (this.hasClampedLines()) {
            this.theadTarget.setAttribute('data-show-more', this.isOpen);
        } else {
            this.theadTarget.removeAttribute('data-show-more');
        }
    }

    hasClampedLines() {
        if (this.isOpen) {
            return false;
        }

        return this.labelTargets.some((label) => {
            return this.isTextClamped(label);
        });
    }

    /**
     * Looking if an element is cut by line-clamp
     */
    isTextClamped(el) {
        return el.scrollHeight > el.clientHeight + 1;
    }
}
