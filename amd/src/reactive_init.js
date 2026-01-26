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


import {Reactive} from 'core/reactive';
import {eventTypes, notifyBlockAiChatStateUpdated} from 'block_ai_chat/events';
import MainComponent from 'block_ai_chat/components/main';
import Mutations from 'block_ai_chat/mutations';
import * as Ajax from 'core/ajax';
import {exception as displayException, alert as alertModal} from 'core/notification';
import Log from 'core/log';
import {RENDER_MODE} from 'block_ai_chat/constants';


/**
 * Main module for the reactive frontend of the block_ai_chat.
 *
 * @module     block_ai_chat/reactive_init
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Initialize the main component.
 *
 * Multiple embedded instances (modal = null) are supported, but only one modal instance (modal object
 * needs to be passed).
 *
 * @param {number} contextid The context id
 * @param {string} mainElementSelector Selector for the main element. Needs to be unique on the page.
 * @param {object|null} modal The modal instance or null for embedded mode.
 * @param {string} component the component name of the plugin from which the AI chat is being used, for example mod_aichat
 * @returns {Promise<MainComponent|undefined>} The initialized main component or undefined on error.
 */
export const init = async(contextid, mainElementSelector, modal = null, component = 'block_ai_chat') => {
    // Just one modal per page is allowed/supported.
    // Multiple embedded instances per page however are allowed/supported.

    const mainElement = document.querySelector(mainElementSelector);
    if (!mainElement) {
        const errormessage = 'No main element found for selector ' + mainElementSelector;
        await alertModal(errormessage);
        return;
    }
    if (modal === null && (!mainElement.dataset.hasOwnProperty('id') || mainElement.dataset.id.length === 0)) {
        const errormessage = 'No data id attribute found on main element for selector ' + mainElementSelector;
        await alertModal(errormessage);
        return;
    }

    // Fetch and prepare the initial state.
    let state = null;
    // This external function is a bit special, so we do not use callExternalFunction from 'block_ai_chat/utils' here.
    try {
        state = await Ajax.call([{
            methodname: 'block_ai_chat_get_initial_state',
            args: {
                contextid,
                component
            }
        }])[0];
    } catch (error) {
        Log.error('Error while retrieving initial state', error);
        await displayException(error);
        return;
    }

    state.static.renderMode = modal === null ? RENDER_MODE.EMBEDDED : RENDER_MODE.MODAL;

    let reactiveChatName;
    if (state.static.renderMode === RENDER_MODE.MODAL) {
        // Only one modal instance per page is allowed, so no need to make the name more unique than that.
        reactiveChatName = 'block_ai_chat_reactive_chat_modal';
    } else {
        // Use the data id attribute to make the name unique for multiple embedded instances.
        // It's basically just for debugging purposes so the reactive instance can be identified
        // as reactive instance of a specific rendering process.
        reactiveChatName = 'block_ai_chat_reactive_chat_embedded_' + mainElement.dataset.id;
    }

    // Create the reactive instance.
    const reactiveChat = new Reactive({
        name: reactiveChatName,
        eventName: eventTypes.blockAiChatStateUpdated,
        eventDispatch: notifyBlockAiChatStateUpdated,
        mutations: new Mutations(),
        state,
    });

    // Spin up the main component for the reactive UI.
    // All further components will be created by the main component itself.
    new MainComponent({
        element: mainElement,
        reactive: reactiveChat,
        modal: modal
    });
};