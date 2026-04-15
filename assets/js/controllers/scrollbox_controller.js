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
    static targets = [
        'scroller',
        'leftObserver',
        'rightObserver',
        'thead',
        'tbody',
        'tfoot',
        'scrollBtns',
        'scrollLeftBtn',
        'scrollRightBtn',
    ]

    scrollers = [this.theadTarget, this.tbodyTarget, this.tfootTarget];
    timeout = null

    connect() {
        this.onScroll = this.onScroll.bind(this)

        const leftIntersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                this.element.dataset.shadowLeft = !entry.isIntersecting;
            });
        }, {
            root: this.scrollerTarget,
            rootMargin: "0px 0px 0px -125px",
            threshold: 1,
        });

        if (this.hasLeftObserverTarget) {
            leftIntersectionObserver.observe(this.leftObserverTarget);
        }

        const rightIntersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                this.element.dataset.shadowRight = !entry.isIntersecting;
            });
        }, {
            root: this.scrollerTarget,
            threshold: 1,
        });

        if (this.hasRightObserverTarget) {
            rightIntersectionObserver.observe(this.rightObserverTarget);
        }

        const topIntersectionObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                this.element.dataset.shadowTop = entry.isIntersecting;
            });
        }, {
            root: this.scrollerTarget,
            rootMargin: '-1px 0px 0px 0px',
            threshold: 1,
        });

        topIntersectionObserver.observe(this.theadTarget);

        this.attachAllExcept();
        this.updateScrollButtonsVisibility();
    }

    attachAllExcept(source) {
        this.scrollers.forEach((el) => {
            if (el !== source) {
                el.addEventListener('scroll', this.onScroll, { passive: true });
            }
        });
    }

    detachAllExcept(source) {
        this.scrollers.forEach((el) => {
            if (el !== source) {
                el.removeEventListener('scroll', this.onScroll, { passive: true });
            }
        });
    }

    scrollLeft() {
        this.scrollTo(-this.element.clientWidth / 2)
    }

    scrollRight() {
        this.scrollTo(this.element.clientWidth / 2)
    }

    scrollTo(x) {
        this.tfootTarget.scrollBy({
            left: x,
            behavior: 'smooth',
        });
    }

    updateScrollButtonsVisibility() {
        const atStart = this.isScrollAtStart(this.tfootTarget);
        const atEnd = this.isScrollAtEnd(this.tfootTarget);

        this.scrollLeftBtnTarget.disabled = atStart;
        this.scrollRightBtnTarget.disabled = atEnd;
        this.scrollLeftBtnTarget.setAttribute('aria-disabled', atStart);
        this.scrollRightBtnTarget.setAttribute('aria-disabled', atEnd);

        if (atStart && atEnd) {
            this.scrollLeftBtnTarget.hidden = true;
            this.scrollLeftBtnTarget.classList.add('hidden');
            this.scrollLeftBtnTarget.setAttribute('aria-hidden', true);
            this.scrollRightBtnTarget.hidden = true;
            this.scrollRightBtnTarget.classList.add('hidden');
            this.scrollRightBtnTarget.setAttribute('aria-hidden', true);
            this.scrollBtnsTarget.classList.add('hidden');
        } else {
            this.scrollLeftBtnTarget.hidden = false;
            this.scrollLeftBtnTarget.classList.remove('hidden');
            this.scrollLeftBtnTarget.setAttribute('aria-hidden', false);
            this.scrollRightBtnTarget.hidden = false;
            this.scrollRightBtnTarget.classList.remove('hidden');
            this.scrollRightBtnTarget.setAttribute('aria-hidden', false);
            this.scrollBtnsTarget.classList.remove('hidden');
        }
    }

    isScrollAtStart(el) {
        return this.getNormalizedScrollLeft(el) <= 1;
    }

    isScrollAtEnd(el) {
        const scrollLeft = this.getNormalizedScrollLeft(el);
        return scrollLeft + el.clientWidth >= el.scrollWidth - 1;
    }

    getNormalizedScrollLeft(el) {
        const dir = getComputedStyle(el).direction;

        if (dir !== 'rtl') {
            return el.scrollLeft;
        }

        return Math.abs(el.scrollLeft);
    }

    onScroll(e) {
        clearTimeout(this.timeout)

        const source = e.currentTarget;

        this.detachAllExcept(source);

        this.scrollers.forEach((el) => {
            if (el !== source && el.scrollLeft !== source.scrollLeft) {
                el.scrollLeft = source.scrollLeft;
            }
        });

        this.timeout = setTimeout(() => {
            this.attachAllExcept(source);
            this.updateScrollButtonsVisibility()
        }, 80)
    }
}
