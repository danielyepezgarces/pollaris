// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert
// SPDX-License-Identifier: AGPL-3.0-or-later

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
