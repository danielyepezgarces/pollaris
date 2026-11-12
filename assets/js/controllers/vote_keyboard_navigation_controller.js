// This file is part of Pollaris.
// Copyright 2026 Daniel Yepez Garces
// SPDX-License-Identifier: AGPL-3.0-or-later

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    currentIndex = -1;

    connect() {
        this._onKeyDown = this.handleKeydown.bind(this);
        this._onFocusIn = this.handleFocusIn.bind(this);

        window.addEventListener('keydown', this._onKeyDown);
        this.element.addEventListener('focusin', this._onFocusIn);
    }

    disconnect() {
        if (this._onKeyDown) {
            window.removeEventListener('keydown', this._onKeyDown);
        }
        if (this._onFocusIn) {
            this.element.removeEventListener('focusin', this._onFocusIn);
        }
    }

    getVisibleContainers() {
        return Array.from(this.element.querySelectorAll('.vote__choices')).filter((el) => {
            return el.offsetParent !== null;
        });
    }

    handleFocusIn(event) {
        const containers = this.getVisibleContainers();
        const idx = containers.findIndex((c) => c.contains(event.target));
        if (idx !== -1) {
            this.currentIndex = idx;
            this.highlightContainer(idx, containers);
        } else {
            containers.forEach((c) => c.classList.remove('is-keyboard-focus'));
        }
    }

    handleKeydown(event) {
        if (!this.element || !this.element.isConnected) return;

        // Ignore if a modal or dialog is open
        if (document.querySelector('.modal.is-open, dialog[open]')) return;

        const target = event.target;
        const isTextInput = target && (
            target.tagName === 'TEXTAREA' ||
            target.isContentEditable ||
            (target.tagName === 'INPUT' && !['radio', 'checkbox', 'button', 'submit'].includes((target.type || '').toLowerCase()))
        );

        // Submit form shortcut (Ctrl+Enter or Cmd+Enter) works everywhere in the form
        const isSubmitKey = (event.ctrlKey || event.metaKey) && event.key === 'Enter';
        if (isSubmitKey) {
            event.preventDefault();
            this.submitForm();
            return;
        }

        // If other modifiers are pressed (Ctrl+C, Alt+Tab, etc.), ignore completely
        if (event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        // Never intercept single-key shortcuts while typing in a text field
        if (isTextInput) {
            return;
        }

        // If focus is on interactive controls outside the form, let native handling proceed
        if (target && target !== document.body && target !== document.documentElement && !this.element.contains(target) && ['A', 'BUTTON', 'SELECT', 'INPUT'].includes(target.tagName)) {
            return;
        }

        const containers = this.getVisibleContainers();
        if (!containers.length) return;

        const key = event.key.toLowerCase();

        switch (key) {
            case 'y':
            case 's': // Spanish: Sí
                event.preventDefault();
                this.voteChoice('radio--vote-yes', containers);
                break;

            case 'n': // No
                event.preventDefault();
                this.voteChoice('radio--vote-no', containers);
                break;

            case 'm': // Maybe
            case 't': // Spanish: Tal vez
                event.preventDefault();
                this.voteChoice('radio--vote-maybe', containers);
                break;

            case 'backspace':
            case 'delete':
                this.clearChoice(containers);
                break;

            case 'arrowright':
            case 'arrowdown':
                event.preventDefault();
                this.navigateNext(containers);
                break;

            case 'arrowleft':
            case 'arrowup':
                event.preventDefault();
                this.navigatePrev(containers);
                break;

            default:
                break;
        }
    }

    voteChoice(choiceClass, containers) {
        let idx = this.currentIndex;
        if (idx < 0 || idx >= containers.length) {
            idx = 0;
        }

        const container = containers[idx];
        const radio = container.querySelector(`input.${choiceClass}`);
        if (!radio || radio.disabled) {
            return;
        }

        radio.focus();
        radio.checked = true;
        radio.dispatchEvent(new Event('input', { bubbles: true }));
        radio.dispatchEvent(new Event('change', { bubbles: true }));

        this.pulseContainer(container);
        this.advance(idx, containers);
    }

    clearChoice(containers) {
        let idx = this.currentIndex;
        if (idx < 0 || idx >= containers.length) return;

        const container = containers[idx];
        const checked = container.querySelector('input[type="radio"]:checked');
        if (checked) {
            checked.checked = false;
            checked.dispatchEvent(new Event('input', { bubbles: true }));
            checked.dispatchEvent(new Event('change', { bubbles: true }));
            this.pulseContainer(container);
        }
    }

    navigateNext(containers) {
        let idx = this.currentIndex;
        if (idx < 0) {
            this.focusProposal(0, containers);
        } else if (idx < containers.length - 1) {
            this.focusProposal(idx + 1, containers);
        } else {
            this.focusAfterProposals(containers);
        }
    }

    navigatePrev(containers) {
        let idx = this.currentIndex;
        if (idx <= 0) {
            this.focusProposal(0, containers);
        } else {
            this.focusProposal(idx - 1, containers);
        }
    }

    advance(currentIdx, containers) {
        if (currentIdx < containers.length - 1) {
            this.focusProposal(currentIdx + 1, containers);
        } else {
            this.focusAfterProposals(containers);
        }
    }

    focusProposal(index, containers) {
        index = Math.max(0, Math.min(index, containers.length - 1));
        this.currentIndex = index;
        const container = containers[index];

        this.highlightContainer(index, containers);

        const targetRadio = container.querySelector('input[type="radio"]:checked') ||
                            container.querySelector('input[type="radio"]:not([disabled])') ||
                            container.querySelector('input[type="radio"]');

        if (targetRadio) {
            targetRadio.focus();
        }

        container.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }

    highlightContainer(index, containers) {
        containers.forEach((c, i) => {
            if (i === index) {
                c.classList.add('is-keyboard-focus');
            } else {
                c.classList.remove('is-keyboard-focus');
            }
        });
    }

    pulseContainer(container) {
        container.classList.remove('is-shortcut-pulsing');
        void container.offsetWidth; // Trigger reflow for restart
        container.classList.add('is-shortcut-pulsing');
        setTimeout(() => {
            container.classList.remove('is-shortcut-pulsing');
        }, 220);
    }

    focusAfterProposals(containers) {
        if (containers) {
            containers.forEach((c) => c.classList.remove('is-keyboard-focus'));
        }

        const authorInput = this.element.querySelector('input[name*="[authorName]"]');
        if (authorInput && !authorInput.value.trim() && !authorInput.disabled) {
            authorInput.focus();
            authorInput.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        const submitBtn = this.element.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            submitBtn.focus();
            submitBtn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    submitForm() {
        const submitBtn = this.element.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            submitBtn.click();
        } else {
            this.element.requestSubmit();
        }
    }
}
