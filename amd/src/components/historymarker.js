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

import Popover from 'theme_boost/bootstrap/popover';
import {BaseComponent} from 'core/reactive';


class HistoryMarker extends BaseComponent {

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

    /**
     * It is important to follow some conventions while you write components. This way all components
     * will be implemented in a similar way and anybody will be able to understand how it works.
     *
     * All the component definition should be initialized on the "create" method.
     */
    create() {
        this.name = 'HistoryMarker';
        this.selectors = {
            MESSAGES: `[data-block_ai_chat-element='messages']`,
            MESSAGE: `[data-block_ai_chat-component='message']`,
            HISTORY_MARKER_CONTENT: `[data-block_ai_chat-element='historymarkercontent']`,
        };
    }

    /**
     * Initial state ready method.
     *
     * Initially calls the setView action to set the view to 'chat' once ready to trigger the first
     * rendering of itself.
     */
    async stateReady() {
        // Initialize the popover.
        const historyMarkerContent = this.getElement(this.selectors.HISTORY_MARKER_CONTENT);
        if (historyMarkerContent) {
            new Popover(historyMarkerContent);
        }
        this._updatePositionAndVisibility();
    }

    getWatchers() {
        return [
            {watch: `messages:created`, handler: this._updatePositionAndVisibility},
            {watch: `messages:deleted`, handler: this._updatePositionAndVisibility},
            {watch: `config.conversationContextLimit:updated`, handler: this._updatePositionAndVisibility},
        ];
    }

    _updatePositionAndVisibility() {
        const chatOutputContainer = this.getElement().parentElement;

        const messages = this.reactive.state.messages;
        const contextLimit = this.reactive.state.config.conversationContextLimit;

        if (messages.size <= contextLimit) {
            this._hide();
            return;
        }

        // Set correct marker value:
        this.getElement(this.selectors.HISTORY_MARKER_CONTENT).textContent = contextLimit;

        // Marker position: after message at index (length - 2 * contextLimit - 1).
        // History length of 4 messages actually means 4 user messages and 4 AI messages = 8 messages total.
        const markerPosition = messages.size - 2 * contextLimit - 1;
        const messageId = Array.from(messages.values())[markerPosition].id;

        this.checkAndPositionMarker(chatOutputContainer, messageId);
    }

    checkAndPositionMarker(chatOutputContainer, messageId, attempts = 0) {
        const messageBeforeContext = chatOutputContainer.querySelector(
            this.selectors.MESSAGE + `[data-block_ai_chat-messageid="${messageId}"]`
        );

        if (messageBeforeContext) {
            this._show();
            messageBeforeContext.after(this.getElement());
            return;
        }

        attempts++;
        if (attempts < 5) {
            setTimeout(() => {
                this.checkAndPositionMarker(chatOutputContainer, messageId, attempts);
            }, 500);
        }
    }

    _hide() {
        this.getElement().classList.add('d-none');
    }

    _show() {
        this.getElement().classList.remove('d-none');
    }
}

export default HistoryMarker;
