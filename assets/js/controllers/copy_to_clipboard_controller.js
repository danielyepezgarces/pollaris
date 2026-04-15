// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
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

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static get targets () {
        return ['copyable', 'button'];
    }

    async copy() {
        if (this.copyableTarget instanceof HTMLCanvasElement) {
            const blob = await this.canvasToBlob(this.copyableTarget, 'image/png');

            if (!blob) {
                return;
            }

            try {
                await navigator.clipboard.write([
                    new ClipboardItem({
                        [blob.type]: blob,
                    }),
                ]);

                this.showCopiedLabel();
            } catch (error) {
                await this.downloadBlob();
            }
        } else {
            let text;

            if (this.copyableTarget.hasAttribute('value')) {
                text = this.copyableTarget.getAttribute('value').trim();
            } else {
                text = this.copyableTarget.textContent.trim();
            }

            navigator.clipboard.writeText(text);

            this.showCopiedLabel();
        }
    }

    async downloadBlob({ params: { filename } = {}} = {}) {
        const blob = await this.canvasToBlob(this.copyableTarget, 'image/png');

        if (!blob) {
            return;
        }

        const objectUrl = URL.createObjectURL(blob);
        const a = document.createElement('a');

        a.href = objectUrl;
        a.download = filename || 'qr-code.png';
        document.body.appendChild(a);
        a.click();
        a.remove();

        // Free memory
        setTimeout(() => URL.revokeObjectURL(objectUrl), 10_000);
    }

    showCopiedLabel() {
        const oldButtonTargetText = this.buttonTarget.innerText;
        this.buttonTarget.innerText = this.element.dataset.labelCopied;

        setTimeout(() => {
            this.buttonTarget.innerText = oldButtonTargetText;
        }, 2000);
    }

    canvasToBlob(canvas, type) {
        return new Promise((resolve) => {
            canvas.toBlob((b) => resolve(b), type);
        });
    }
}
