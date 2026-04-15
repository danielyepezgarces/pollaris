// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// SPDX-License-Identifier: AGPL-3.0-or-later

export default function (text) {
    text = text.replaceAll('&', '&amp;');
    text = text.replaceAll('<', '&lt;')
    text = text.replaceAll('>', '&gt;')
    text = text.replaceAll('"', '&quot;')
    text = text.replaceAll("'", '&#039;');
    return text;
}
