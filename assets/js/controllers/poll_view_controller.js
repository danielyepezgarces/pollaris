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

import {Controller} from '@hotwired/stimulus';

export default class extends Controller {
    static get targets() {
        return [
            'listButton',
            'tableButton',
            'listView',
            'tableView',
            'voteForm',
            'listVoteSlot',
            'tableVoteSlot',
            'mainFlow',
            'thead'
        ];
    }

    connect() {
        this._onTurboRender = this.update.bind(this);

        document.addEventListener('turbo:render', this._onTurboRender);

        this.update();
    }

    disconnect() {
        document.removeEventListener('turbo:render', this._onTurboRender);
    }

    getPreferredView() {
        const preferredView = localStorage.getItem('preferred-poll-view');

        if (preferredView === 'list' || preferredView === 'table') {
            return preferredView;
        }

        return window.innerWidth <= 600 ? 'list' : 'table';
    }

    displayList(e) {
        localStorage.setItem('preferred-poll-view', 'list');

        this.listVoteSlotTargets.forEach((slot) => {
            const proposal = slot.dataset.proposal;
            const voteForm = this.getVoteFormForProposal(proposal);
            if (voteForm) {
                slot.appendChild(voteForm);
            }
        });

        this.listButtonTarget.style.display = 'none';
        this.listViewTarget.hidden = false;

        this.tableButtonTarget.style.display = 'inline-block';
        this.tableViewTarget.hidden = true;

        document.body.classList.add('poll-view--list');
        document.body.classList.remove('poll-view--table');

        if (e !== undefined) {
            this.tableButtonTarget.focus();
        }
    }

    displayTable(e) {
        localStorage.setItem('preferred-poll-view', 'table');

        this.tableVoteSlotTargets.forEach((slot) => {
            const proposal = slot.dataset.proposal;
            const voteForm = this.getVoteFormForProposal(proposal);
            if (voteForm) {
                slot.appendChild(voteForm);
            }
        });

        this.listButtonTarget.style.display = 'inline-block';
        this.listViewTarget.hidden = true;

        this.tableButtonTarget.style.display = 'none';
        this.tableViewTarget.hidden = false;

        document.body.classList.add('poll-view--table');
        document.body.classList.remove('poll-view--list');

        if (e !== undefined) {
            this.listButtonTarget.focus();
        }
    }

    update() {
        const preferredView = this.getPreferredView();

        if (preferredView === 'list') {
            this.displayList();
        } else {
            this.displayTable();
        }

        this.setPollWidth();
    }

    getVoteFormForProposal(proposal) {
        return this.voteFormTargets.find((form) => {
            return form.dataset.proposal === proposal;
        });
    }

    setPollWidth() {
        const mainFlowEl = document.getElementById('mainFlow');
        const mainFlowWidth = mainFlowEl.clientWidth;
        const theadLineEl = this.theadTarget.querySelector('tr');
        const theadLineWidth = theadLineEl?.clientWidth || null;
        const rootStyles = getComputedStyle(document.documentElement);
        const borderSize = rootStyles.getPropertyValue('--poll-outer-border-size').trim();

        let pollWidth = Math.min(mainFlowWidth, (theadLineWidth + (parseInt(borderSize) * 2)))

        if (mainFlowWidth < 650 && pollWidth < 650) {
            pollWidth = mainFlowWidth
        }

        if (theadLineWidth < 650 && mainFlowWidth >= 650) {
            document.documentElement.style.setProperty('--poll-width', `650px`);
        } else {
            document.documentElement.style.setProperty('--poll-width', `calc(${pollWidth}px`);
        }
    }
}
