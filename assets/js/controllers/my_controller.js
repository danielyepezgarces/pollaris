// This file is part of Pollaris.
// Copyright 2024-2026 Marien Fressinaud
// Copyright 2026 Adrien Scholaert
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';
import dayjs from 'dayjs';

import { setDayjsLocale } from '../dayjs_locale.js';
import * as Storage from '../storage.js';
import htmlEscape from '../html_escape.js';

export default class extends Controller {
    static targets = [
        'polls',
        'pollsRemovalButton',
        'pollsPlaceholder',
        'pollTemplate',
        'votes',
        'votesRemovalButton',
        'votesPlaceholder',
        'voteTemplate',
    ]

    static values = {
        confirmationPollsRemoval: String,
        confirmationVotesRemoval: String,
    }

    connect () {
        setDayjsLocale(dayjs);

        this.cleanup('votes').finally(() => {
            this.refreshMyVotes();
        });

        this.refreshMyPolls();
    }

    async cleanup(storageKey) {
        const data = Storage.listEntries(storageKey);
        const checks = data.map(async ([key, item]) => {
            const pollUrl = item && typeof item.pollUrl === 'string' ? item.pollUrl : null;

            if (!pollUrl) {
                return;
            }

            // Only validate same-origin poll URLs to avoid leaking requests to external hosts
            const url = this.safeParseUrl(pollUrl);

            if (!url || url.origin !== window.location.origin) {
                return;
            }

            // Only check poll pages
            if (!url.pathname.startsWith('/polls/')) {
                return;
            }

            const status = await this.fetchStatus(url.toString());

            if (status === 404) {
                Storage.unstoreEntry(storageKey, key);
            }
        });

        await Promise.all(checks);
    }

    async fetchStatus (url) {
        try {
            const headResponse = await fetch(url, {
                method: 'HEAD',
                credentials: 'same-origin',
                redirect: 'manual',
                cache: 'no-store',
            });

            // Some servers/proxies may not support HEAD correctly; fallback to GET
            if (headResponse.status === 405 || headResponse.status === 501) {
                const getResponse = await fetch(url, {
                    method: 'GET',
                    credentials: 'same-origin',
                    redirect: 'manual',
                    cache: 'no-store',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                return getResponse.status;
            }

            return headResponse.status;
        } catch (e) {
            // Network error
            return null;
        }
    }

    safeParseUrl (url) {
        try {
            return new URL(url, window.location.origin);
        } catch (e) {
            return null;
        }
    }

    refreshMyPolls () {
        const pollTemplate = this.pollTemplateTarget.content.firstElementChild;
        const myPolls = Storage.listEntries('polls');

        this.pollsTarget.innerHTML = '';

        myPolls.forEach(([key, myPoll]) => {
            const pollNode = pollTemplate.cloneNode(true);
            const pollDate = dayjs(myPoll.date);
            const formattedDate = pollDate.format('DD MMM YYYY');

            pollNode.innerHTML = pollNode.innerHTML.replace(/__key__/g, htmlEscape(key));
            pollNode.innerHTML = pollNode.innerHTML.replace(/__title__/g, htmlEscape(myPoll.title));
            pollNode.innerHTML = pollNode.innerHTML.replace(/__date__/g, htmlEscape(formattedDate));
            pollNode.innerHTML = pollNode.innerHTML.replace(/__author__/g, this.wikimediaUserLink(myPoll.author));
            pollNode.innerHTML = pollNode.innerHTML.replace(/__poll_url__/g, htmlEscape(myPoll.pollUrl));
            pollNode.innerHTML = pollNode.innerHTML.replace(/__admin_url__/g, htmlEscape(myPoll.adminUrl));
            this.pollsTarget.appendChild(pollNode);
        });

        if (myPolls.length === 0) {
            this.pollsPlaceholderTarget.style.display = 'block';
            this.pollsTarget.style.display = 'none';
            this.pollsRemovalButtonTarget.style.display = 'none';
        } else {
            this.pollsPlaceholderTarget.style.display = 'none';
            this.pollsTarget.style.display = 'block';
            this.pollsRemovalButtonTarget.style.display = 'inline-block';
        }
    }

    removePoll (event) {
        const key = event.target.dataset.storageKey;
        Storage.unstoreEntry('polls', key);
        this.refreshMyPolls();
    }

    removePolls () {
        if (confirm(this.confirmationPollsRemovalValue)) {
            Storage.deleteNamespace('polls');
            this.refreshMyPolls();
        }
    }

    refreshMyVotes () {
        const voteTemplate = this.voteTemplateTarget.content.firstElementChild;
        const myVotes = Storage.listEntries('votes');

        this.votesTarget.innerHTML = '';

        myVotes.forEach(([key, myVote]) => {
            const voteNode = voteTemplate.cloneNode(true);
            const voteDate = dayjs(myVote.voteDate);
            const formattedDate = voteDate.format('DD MMM YYYY');

            voteNode.innerHTML = voteNode.innerHTML.replace(/__key__/g, htmlEscape(key));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__vote_date__/g, htmlEscape(formattedDate));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__vote_author__/g, this.wikimediaUserLink(myVote.voteAuthor));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__vote_url__/g, htmlEscape(myVote.voteUrl));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__poll_title__/g, htmlEscape(myVote.pollTitle));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__poll_author__/g, this.wikimediaUserLink(myVote.pollAuthor));
            voteNode.innerHTML = voteNode.innerHTML.replace(/__poll_url__/g, htmlEscape(myVote.pollUrl));

            if (!myVote.canEditVote) {
                const linkToVote = voteNode.querySelector('[data-link-to-vote]');
                linkToVote.style.display = 'none';
            }

            this.votesTarget.appendChild(voteNode);
        });

        if (myVotes.length === 0) {
            this.votesPlaceholderTarget.style.display = 'block';
            this.votesTarget.style.display = 'none';
            this.votesRemovalButtonTarget.style.display = 'none';
        } else {
            this.votesPlaceholderTarget.style.display = 'none';
            this.votesTarget.style.display = 'block';
            this.votesRemovalButtonTarget.style.display = 'inline-block';
        }
    }

    removeVote (event) {
        const key = event.target.dataset.storageKey;
        Storage.unstoreEntry('votes', key);
        this.refreshMyVotes();
    }

    removeVotes () {
        if (confirm(this.confirmationVotesRemovalValue)) {
            Storage.deleteNamespace('votes');
            this.refreshMyVotes();
        }
    }

    wikimediaUserLink (username) {
        const encodedUsername = encodeURIComponent(String(username ?? '').replaceAll(' ', '_'));
        const url = `https://meta.wikimedia.org/wiki/User:${encodedUsername}`;

        return `<a href="${htmlEscape(url)}" target="_blank" rel="noopener noreferrer">${htmlEscape(username ?? '')}</a>`;
    }
}
