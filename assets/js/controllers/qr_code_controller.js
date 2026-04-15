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

import { Controller } from '@hotwired/stimulus';

import QRCode from 'qrcode';

export default class extends Controller {
    static values = {
        url: String,
    };

    connect() {
        try {
            QRCode.toCanvas(this.element, this.urlValue, {
                errorCorrectionLevel: 'Q',
                width: 150,
            });
        } catch (error) {
            console.error('Failed to generate QR code', error);
        }
    }
}
