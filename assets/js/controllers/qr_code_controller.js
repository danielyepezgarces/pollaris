// This file is part of Pollaris.
// Copyright 2026 Adrien Scholaert
// SPDX-License-Identifier: AGPL-3.0-or-later

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
