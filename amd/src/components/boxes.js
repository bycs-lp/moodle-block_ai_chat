// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

import {BaseComponent} from 'core/reactive';
import {renderInfoBox} from 'local_ai_manager/infobox';
import {renderWarningBox} from 'local_ai_manager/warningbox';
import {eventTypes} from 'block_ai_chat/events';

/**
 * Component representing a message in the ai_chat.
 */
class Boxes extends BaseComponent {

    /**
     * Function to initialize component, called by mustache template.
     *
     * @param {*} target The id of the HTMLElement to attach to
     * @returns {BaseComponent} New component attached to the HTMLElement represented by target
     */
    static init(target) {
        let element = document.querySelector(target);
        return new this({
            element: element,
        });
    }

    create() {
        this.name = 'userquota';
        this.selectors = {
            INFOBOX: `[data-block_ai_chat-element='ai_manager-infobox']`,
            WARNINGBOX: `[data-block_ai_chat-element='ai_manager-warningbox']`,
            CHAT_OUTPUT_WRAPPER: `[data-block_ai_chat-element='outputwrapper']`,
        };
        this.HIDE_CLASS = 'block_ai_chat-scroll-hide';
        this._scrollListenerAttached = false;
        window.console.log('[Boxes] create() called');
    }

    getWatchers() {
        return [
            {watch: `config.view:updated`, handler: this._showOrHideBoxes},
            {watch: `config.currentConversationId:updated`, handler: this._onConversationChanged},
        ];
    }

    async stateReady() {
        window.console.log('[Boxes] stateReady() called');
        await this._renderBoxes();
        this._showOrHideBoxes();

        // Find the modal container and listen for chatContentRendered events.
        const modal = this.element.closest('.block_ai_chat_reactive_main_component');
        if (modal) {
            this.addEventListener(modal, eventTypes.chatContentRendered, () => this._onChatContentRendered());
        }
    }

    /**
     * Called when chat content has been rendered.
     * Now we can safely attach the scroll listener to the correct element.
     */
    _onChatContentRendered() {
        window.console.log('[Boxes] Chat content rendered event received');
        // Reset the flag so we can attach to the new element.
        this._scrollListenerAttached = false;
        this._attachScrollListener();
    }

    /**
     * Attach scroll listener to the chat output container.
     * The warning will hide when the user scrolls in the chat.
     */
    _attachScrollListener() {
        if (this._scrollListenerAttached) {
            window.console.log('[Boxes] Scroll listener already attached, skipping');
            return;
        }

        // Find the modal container (boxes is in header, chat output is in body).
        const modal = this.element.closest('.block_ai_chat_reactive_main_component');
        if (!modal) {
            window.console.log('[Boxes] Could not find modal container');
            return;
        }

        const chatOutputWrapper = modal.querySelector(this.selectors.CHAT_OUTPUT_WRAPPER);
        if (chatOutputWrapper) {
            this._attachScrollListenerToElement(chatOutputWrapper);
            return;
        }

        // Chat output not available yet - will be handled by chatContentRendered event.
        window.console.log('[Boxes] Chat output not rendered yet, waiting for chatContentRendered event');
    }

    /**
     * Attach the scroll listener to the given element.
     *
     * @param {HTMLElement} chatOutputWrapper The chat output wrapper element
     */
    _attachScrollListenerToElement(chatOutputWrapper) {
        if (this._scrollListenerAttached) {
            return;
        }
        window.console.log('[Boxes] Attaching scroll listener to:', chatOutputWrapper);

        // Store initial scroll position to detect user-initiated scrolls.
        // Use a small delay to let initial layout/auto-scroll settle.
        this._ignoreScrollEvents = true;
        setTimeout(() => {
            this._ignoreScrollEvents = false;
            this._lastScrollTop = chatOutputWrapper.scrollTop;
            window.console.log('[Boxes] Scroll detection enabled, initial scrollTop:', this._lastScrollTop);
        }, 500);

        this.addEventListener(chatOutputWrapper, 'scroll', () => this._onScroll(chatOutputWrapper));
        this._scrollListenerAttached = true;
    }

    /**
     * Handle scroll event - hide the boxes when user actively scrolls.
     * Only triggers when there is a visible scrollbar and user scrolls away from bottom.
     *
     * @param {HTMLElement} chatOutputWrapper The chat output wrapper container
     */
    _onScroll(chatOutputWrapper) {
        // Ignore scroll events during initial layout/auto-scroll period.
        if (this._ignoreScrollEvents) {
            window.console.log('[Boxes] Ignoring scroll event during initial layout');
            return;
        }

        // Check if scrollbar is actually visible (content overflows the container).
        const hasVisibleScrollbar = chatOutputWrapper.scrollHeight > chatOutputWrapper.clientHeight;

        // Check if we're at the bottom (auto-scroll position) or user scrolled away.
        // Auto-scroll always goes to bottom, so if user is NOT at bottom, they scrolled.
        const distanceFromBottom = chatOutputWrapper.scrollHeight - chatOutputWrapper.scrollTop - chatOutputWrapper.clientHeight;
        const isAtBottom = distanceFromBottom < 10; // Allow small tolerance

        window.console.log('[Boxes] Scroll event fired!', {
            scrollTop: chatOutputWrapper.scrollTop,
            scrollHeight: chatOutputWrapper.scrollHeight,
            clientHeight: chatOutputWrapper.clientHeight,
            distanceFromBottom: distanceFromBottom,
            isAtBottom: isAtBottom,
            hasVisibleScrollbar: hasVisibleScrollbar
        });

        // Only hide if there's a visible scrollbar AND user scrolled away from bottom.
        // This ensures we don't trigger on auto-scroll (which always goes to bottom).
        if (hasVisibleScrollbar && !isAtBottom) {
            window.console.log('[Boxes] Hiding boxes - user scrolled away from bottom');
            this.element.classList.add(this.HIDE_CLASS);
        }
    }

    async _renderBoxes() {
        await renderInfoBox(
            'block_ai_chat',
            this.reactive.state.config.userid,
            this.getElement(this.selectors.INFOBOX),
            ['chat']
        );
        // Show AI info warning.
        await renderWarningBox(this.getElement(this.selectors.WARNINGBOX));
    }

    _showOrHideBoxes() {
        if (this.reactive.state.config.view === 'chat') {
            // Reset visibility and remove hide animation class.
            this.element.classList.remove('d-none', this.HIDE_CLASS);
            // Reset scroll listener so it can fire again when view changes.
            this._scrollListenerAttached = false;
            this._attachScrollListener();
        } else {
            this.element.classList.add('d-none');
        }
    }

    /**
     * Called when the conversation changes (new chat created or old chat selected).
     * Resets the boxes visibility and reattaches the scroll listener.
     */
    _onConversationChanged() {
        window.console.log('[Boxes] Conversation changed, resetting boxes', {
            view: this.reactive.state.config.view,
            conversationId: this.reactive.state.config.currentConversationId,
            scrollListenerAttached: this._scrollListenerAttached
        });
        // Only reset if we're in chat view.
        if (this.reactive.state.config.view === 'chat') {
            // Show boxes again.
            this.element.classList.remove(this.HIDE_CLASS);
            // Reset scroll listener so it can fire again for the new conversation.
            this._scrollListenerAttached = false;
            this._attachScrollListener();
        }
    }
}

export default Boxes;
