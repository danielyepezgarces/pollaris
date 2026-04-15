// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
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

/**
 * Attempts to show the poll description in the viewer's browser language.
 *
 * Strategy (in order):
 * 1. Exact or language-prefix match in stored localizedDescriptions
 * 2. Chrome built-in Translator API to translate from the best available source
 * 3. Fall back silently — the server-rendered default description stays visible
 */
export default class extends Controller {
    static targets = ['content', 'loading'];

    static values = {
        descriptions: { type: Array, default: [] },
    };

    async connect() {
        if (this.descriptionsValue.length === 0) {
            return;
        }

        // Prefer app-selected language (html[lang]), fallback to browser language.
        const lang = this.#normaliseLocale(
            document.documentElement.lang || navigator.language || 'en',
        );
        const langShort = lang.split('-')[0];

        // 1. Find a stored description that matches the browser language
        const match = this.#findMatch(lang, langShort);
        if (match) {
            this.#setContent(match.text);
            return;
        }

        // 2. Try Chrome Translator API
        if (!('Translator' in self)) {
            return;
        }

        const source = this.descriptionsValue[0];
        const sourceLang = this.#normaliseLocale(source.locale);

        // Normalise target: zh-hans → zh-Hans for the API
        const targetLang = this.#normaliseTranslatorTarget(langShort, lang);

        try {
            const availability = await Translator.availability({
                sourceLanguage: sourceLang,
                targetLanguage: targetLang,
            });

            if (availability === 'unavailable') {
                return;
            }

            this.#showLoading(true);

            const translator = await Translator.create({
                sourceLanguage: sourceLang,
                targetLanguage: targetLang,
                monitor(m) {
                    m.addEventListener('downloadprogress', (e) => {
                        // progress is exposed but not required to handle
                        void e;
                    });
                },
            });

            const translated = await translator.translate(source.text);
            translator.destroy();

            this.#setContent(translated);
        } catch (_e) {
            // Silently fall back to server-rendered default
        } finally {
            this.#showLoading(false);
        }
    }

    // -------------------------------------------------------------------------

    /** Find the best locale match in stored descriptions */
    #findMatch(lang, langShort) {
        // Exact match first (e.g. target 'pt-br' matches stored 'pt-br')
        let found = this.descriptionsValue.find(
            (d) => this.#normaliseLocale(d.locale) === lang,
        );
        if (found) return found;

        // Short-code match (e.g. target 'pt-br' matches stored 'pt')
        found = this.descriptionsValue.find(
            (d) => this.#normaliseLocale(d.locale).split('-')[0] === langShort,
        );
        return found ?? null;
    }

    /** Normalise locale to lowercase BCP-47-like form */
    #normaliseLocale(value) {
        return String(value || 'en').trim().toLowerCase().replace(/_/g, '-');
    }

    /** Normalise browser locale to a BCP-47 code the Translator API accepts */
    #normaliseTranslatorTarget(short, full) {
        const fullLower = full.toLowerCase();
        if (fullLower.startsWith('zh-hant') || fullLower.startsWith('zh-tw') || fullLower.startsWith('zh-hk')) {
            return 'zh-Hant';
        }
        return short;
    }

    /** Replace the content target with escaped plain text (newlines → <br>) */
    #setContent(text) {
        const container = document.createElement('div');
        container.textContent = text;
        const escaped = container.innerHTML.replace(/\n/g, '<br>');
        this.contentTarget.innerHTML = escaped;
    }

    #showLoading(visible) {
        if (this.hasLoadingTarget) {
            this.loadingTarget.hidden = !visible;
        }
    }
}
