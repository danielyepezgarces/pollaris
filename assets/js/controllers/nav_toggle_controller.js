// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["nav", "button"];

    toggle() {
        const isOpen = this.navTarget.classList.toggle("is-open");
        this.buttonTarget.setAttribute("aria-expanded", String(isOpen));
    }
}
