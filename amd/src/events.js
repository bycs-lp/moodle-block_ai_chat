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

import {dispatchEvent} from 'core/event_dispatcher';

/**
 * Javascript events for the block_ai_chat block.
 *
 * @module     block_ai_chat/events
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Events for the block_ai_chat block.
 *
 * @constant
 * @property {String} blockAiChatStateUpdated See {@link event:blockAiChatStateUpdated}
 */
export const eventTypes = {
    /**
     * Event triggered when the block_ai_chat reactive state is updated.
     *
     * @event blockAiChatStateUpdated
     * @type {CustomEvent}
     * @property {Array} nodes The list of parent nodes which were updated
     */
    blockAiChatStateUpdated: 'block_ai_chat/stateUpdated',
    /**
     * Event triggered when a message has been rendered to the DOM.
     *
     * @event messageRendered
     * @type {CustomEvent}
     * @property {object} detail The message details
     */
    messageRendered: 'block_ai_chat/messageRendered',
};

/**
 * Trigger an event to indicate that the activity state is updated.
 *
 * @method blockAiChatStateUpdated
 * @param {object} detail the full state
 * @param {HTMLElement} container the custom event target (document if none provided)
 * @returns {CustomEvent}
 * @fires blockAiChatStateUpdated
 */
export const notifyBlockAiChatStateUpdated = (detail, container) => {
    return dispatchEvent(eventTypes.blockAiChatStateUpdated, detail, container);
};

/**
 * Trigger an event to indicate that a message has been rendered to the DOM.
 *
 * @method notifyMessageRendered
 * @param {object} detail the message details
 * @param {HTMLElement} container the custom event target (document if none provided)
 * @returns {CustomEvent}
 * @fires messageRendered
 */
export const notifyMessageRendered = (detail, container) => {
    return dispatchEvent(eventTypes.messageRendered, detail, container);
};
