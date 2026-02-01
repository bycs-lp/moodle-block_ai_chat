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

import {callExternalFunctionReactiveUpdate} from 'block_ai_chat/utils';

/**
 * Mutations for the AI Chat block.
 *
 * @module     block_ai_chat/mutations
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
export default class {

    async selectCurrentPersona(stateManager, personaid) {
        let ajaxresult = await callExternalFunctionReactiveUpdate('block_ai_chat_select_persona',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component,
                personaid,
            }
        );
        if (ajaxresult === null) {
            return;
        }
        stateManager.processUpdates(ajaxresult);
    }

    async selectCurrentPersonaAndLoadChat(stateManager, personaid) {
        await this.selectCurrentPersona(stateManager, personaid);
        await this.setView(stateManager, 'chat');
    }

    async submitAiRequest(stateManager, prompt, additionalOptions) {
        this.setLoadingState(stateManager, true);
        const options = {
            conversationid: stateManager.state.config.currentConversationId,
            ...additionalOptions
        };

        const requestOptions = JSON.stringify(options);
        const result = await callExternalFunctionReactiveUpdate('block_ai_chat_request_ai',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component,
                mode: stateManager.state.config.mode,
                prompt: prompt,
                options: requestOptions
            }
        );
        if (result === null) {
            this.setLoadingState(stateManager, false);
            return;
        }
        this.setLoadingState(stateManager, false);
        stateManager.processUpdates(result);
        if (stateManager.state.config.currentConversationId === 0) {
            // If this is the first message in a conversation, the conversation id is still 0.
            // After first message we have to fix that in our local state.
            stateManager.setReadOnly(false);
            stateManager.state.config.currentConversationId = stateManager.state.messages.values().next().value.conversationid;
            stateManager.setReadOnly(true);
        }
    }

    setLoadingState(stateManager, isLoading) {
        stateManager.setReadOnly(false);
        stateManager.state.config.loadingState = isLoading;
        stateManager.setReadOnly(true);
    }

    async setCurrentConversation(stateManager, conversationid) {
        stateManager.setReadOnly(false);
        stateManager.state.config.currentConversationId = conversationid;
        stateManager.setReadOnly(true);
    }

    setView(stateManager, view) {
        if (stateManager.state.config.view === view) {
            return;
        }
        stateManager.setReadOnly(false);
        stateManager.state.config.view = view;
        stateManager.setReadOnly(true);
    }

    async createAndViewNewConversation(stateManager) {
        await this.setConversationAndLoadChat(stateManager, 0);
    }

    async setConversationAndLoadChat(stateManager, conversationid) {
        await this.setCurrentConversation(stateManager, conversationid);
        stateManager.setReadOnly(false);
        stateManager.state.config.view = 'dummy';
        stateManager.setReadOnly(true);
        stateManager.setReadOnly(false);
        stateManager.state.config.view = 'chat';
        stateManager.setReadOnly(true);
    }

    async loadCurrentConversationMessages(stateManager) {
        let deleteActions = [];

        // There probably isn't a better way to remove all messages while triggering all
        // necessary state updates.
        stateManager.state.messages.forEach(message => {
            deleteActions.push(
                {
                    "name": "messages",
                    "action": "remove",
                    "fields":
                        {
                            "id": message.id
                        }
                });
        });
        stateManager.processUpdates(deleteActions);

        if (stateManager.state.config.currentConversationId === 0) {
            return;
        }
        const messages = await callExternalFunctionReactiveUpdate(
            'block_ai_chat_get_messages',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component,
                conversationid: stateManager.state.config.currentConversationId
            }
        );
        if (messages === null) {
            return;
        }
        stateManager.processUpdates(messages);
    }

    markPersona(stateManager, personaId) {
        stateManager.setReadOnly(false);
        stateManager.state.config.currentlyMarkedPersona = personaId;
        stateManager.setReadOnly(true);
    }

    async createNewDummyPersona(stateManager) {
        let ajaxresult = await callExternalFunctionReactiveUpdate(
            'block_ai_chat_create_dummy_persona',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component
            }
        );
        if (ajaxresult === null) {
            return;
        }
        stateManager.processUpdates(ajaxresult);
    }

    async duplicatePersona(stateManager, personaid) {
        let ajaxresult = await callExternalFunctionReactiveUpdate(
            'block_ai_chat_duplicate_persona',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component,
                personaid
            }
        );
        if (ajaxresult === null) {
            return;
        }
        stateManager.processUpdates(ajaxresult);
    }

    async deletePersona(stateManager, personaid) {
        let ajaxresult = await callExternalFunctionReactiveUpdate(
            'block_ai_chat_delete_persona',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component,
                personaid,
            }
        );
        if (ajaxresult === null) {
            return;
        }
        stateManager.processUpdates(ajaxresult);
    }

    processDynamicFormUpdates(stateManager, stateUpdates) {
        stateUpdates.map(update => {
            if (typeof update.fields !== 'object') {
                update.fields = JSON.parse(update.fields);
            }
            return update;
        });
        stateManager.processUpdates(stateUpdates);
    }

    async deleteCurrentConversation(stateManager) {
        let ajaxresult = await callExternalFunctionReactiveUpdate(
            'block_ai_chat_delete_conversation',
            {
                contextid: stateManager.state.static.contextid,
                component: stateManager.state.static.component,
                conversationid: stateManager.state.config.currentConversationId
            }
        );
        if (ajaxresult === null) {
            return;
        }
        // We intentionally do not process the updates, because we currently are removing messages anyway
        // before reloading when (re)loading the chat component.
        await this.createAndViewNewConversation(stateManager);
    }

    setWindowMode(stateManager, windowmode) {
        stateManager.setReadOnly(false);
        stateManager.state.config.windowMode = windowmode;
        stateManager.setReadOnly(true);
    }

    setModalVisibility(stateManager, visible = null) {
        stateManager.setReadOnly(false);
        stateManager.state.config.modalVisible = visible === null ? !stateManager.state.config.modalVisible : visible;
        stateManager.setReadOnly(true);
    }

    setMode(stateManager, mode) {
        stateManager.setReadOnly(false);
        stateManager.state.config.mode = mode;
        stateManager.setReadOnly(true);
    }

    /**
     * When inserting a message, we need to set its rendered state after it has been added to the DOM.
     * This is being done by this mutation which needs to be called from the component after rendering.
     *
     * @param {Object} stateManager the state manager
     * @param {int} messageid the id of the message that has been rendered
     */
    setMessageRendered(stateManager, messageid) {
        stateManager.setReadOnly(false);
        const message = stateManager.state.messages.get(messageid);
        if (message) {
            message.rendered = true;
            stateManager.state.messages.set(messageid, message);
        }
        stateManager.setReadOnly(true);
    }
}
