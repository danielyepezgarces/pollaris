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
import dayjs from 'dayjs';

import { setDayjsLocale } from '../dayjs_locale.js';
import * as Storage from '../storage.js';
import htmlEscape from '../html_escape.js';

export default class extends Controller {
    static targets = ['list', 'template']

    static values = {
        pollId: String,
    }

    connect () {
        setDayjsLocale(dayjs);

        this.refreshMyVotes();
    }

    refreshMyVotes () {
        const voteTemplate = this.templateTarget.content.firstElementChild;
        const myVotes = Storage.listEntries('votes').filter(([key, vote]) => {
            return vote.pollId === this.pollIdValue;
        });

        this.listTarget.innerHTML = '';

        myVotes.forEach(([key, myVote]) => {
            const voteNode = voteTemplate.cloneNode(true);
            const voteDate = dayjs(myVote.date);
            const formattedDate = voteDate.format('DD MMM YYYY');

            voteNode.innerHTML = voteNode.innerHTML.replace(/__key__/g, htmlEscape(key));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__author__/g, htmlEscape(myVote.voteAuthor));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__date__/g, htmlEscape(formattedDate));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__vote_url__/g, htmlEscape(myVote.voteUrl));

            this.listTarget.appendChild(voteNode);
        });

        if (myVotes.length === 0) {
            this.element.hidden = true;
        } else {
            this.element.hidden = false;
        }
    }

    removeVote (event) {
        const key = event.target.dataset.storageKey;
        Storage.unstoreEntry('votes', key);
        this.refreshMyVotes();
    }
}
