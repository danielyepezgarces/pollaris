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
    static targets = ['textarea', 'link'];
    static values = {
        subject: String,
        to: { type: String, default: '' },
    };

    updateLink() {
        if (!this.hasTextareaTarget || !this.hasLinkTarget) {
            return;
        }

        const subject = this.hasSubjectValue ? this.subjectValue : '';
        const body = this.textareaTarget.value ?? '';
        const encodedSubject = encodeURIComponent(subject);
        const encodedBody = encodeURIComponent(body);

        this.linkTarget.href = `mailto:?subject=${encodedSubject}&body=${encodedBody}`;
    }
}
