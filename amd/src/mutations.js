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
import {call as fetchMany} from 'core/ajax';
import {exception as displayException} from 'core/notification';

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
     * Approve a pending tool call (MBS-10761 tool-agent flow) and resume the run.
     *
     * @param {Object} stateManager the state manager
     * @param {number} runid the agent run id
     * @param {number} callindex the pending tool call index
     * @param {string} token the HMAC approval token returned by the backend
     */
    async approveToolCall(stateManager, runid, callindex, token) {
        this.setLoadingState(stateManager, true);
        try {
            await fetchMany([{
                methodname: 'local_ai_manager_agent_approve_tool_call',
                args: {runid, callindex, token},
            }])[0];
        } catch (ex) {
            this.setLoadingState(stateManager, false);
            await displayException(ex);
            return;
        }
        this.setLoadingState(stateManager, false);
        await this.resumeAgentRun(stateManager, runid);
    }

    /**
     * Reject a pending tool call and resume the run so the LLM can recover.
     *
     * @param {Object} stateManager the state manager
     * @param {number} runid the agent run id
     * @param {number} callindex the pending tool call index
     * @param {string} reason optional user reason
     */
    async rejectToolCall(stateManager, runid, callindex, reason = '') {
        this.setLoadingState(stateManager, true);
        try {
            await fetchMany([{
                methodname: 'local_ai_manager_agent_reject_tool_call',
                args: {runid, callindex, reason},
            }])[0];
        } catch (ex) {
            this.setLoadingState(stateManager, false);
            await displayException(ex);
            return;
        }
        this.setLoadingState(stateManager, false);
        await this.resumeAgentRun(stateManager, runid);
    }

    /**
     * Mark a tool as always-trusted for the current session, user or tenant.
     *
     * @param {Object} stateManager the state manager
     * @param {string} toolname the frankenstyle tool name
     * @param {string} scope one of "session", "user", "tenant"
     */
    async trustTool(stateManager, toolname, scope) {
        try {
            await fetchMany([{
                methodname: 'local_ai_manager_agent_trust_tool',
                args: {toolname, scope},
            }])[0];
        } catch (ex) {
            await displayException(ex);
        }
    }

    /**
     * Abort an in-flight or awaiting-approval agent run (MBS-10761 Paket 2).
     *
     * @param {Object} stateManager the state manager
     * @param {number} runid the agent run id
     */
    async abortAgentRun(stateManager, runid) {
        try {
            await fetchMany([{
                methodname: 'local_ai_manager_agent_abort_run',
                args: {runid},
            }])[0];
        } catch (ex) {
            await displayException(ex);
            return;
        }
        stateManager.setReadOnly(false);
        if (stateManager.state.agent) {
            stateManager.state.agent.currentRunId = null;
            stateManager.state.agent.pendingApprovals = [];
            stateManager.state.agent.progressText = '';
        }
        stateManager.setReadOnly(true);
        this.setLoadingState(stateManager, false);
    }

    /**
     * Undo a reversible tool call within the configured window (MBS-10761 Paket 2).
     *
     * @param {Object} stateManager the state manager
     * @param {number} callid the tool call id
     */
    async undoToolResult(stateManager, callid) {
        let response;
        try {
            response = await fetchMany([{
                methodname: 'local_ai_manager_agent_undo_tool_result',
                args: {callid},
            }])[0];
        } catch (ex) {
            await displayException(ex);
            return;
        }
        stateManager.setReadOnly(false);
        if (stateManager.state.agent && Array.isArray(stateManager.state.agent.lastResults)) {
            stateManager.state.agent.lastResults = stateManager.state.agent.lastResults.map((entry) => {
                if (entry.callid === callid) {
                    return {...entry, undone: true, undone_at: response ? response.undone_at : 0};
                }
                return entry;
            });
        }
        stateManager.setReadOnly(true);
    }

    /**
     * Client-side timer tick used by the undo-button countdown watcher.
     *
     * Decrements `secondsLeft` on every `state.agent.lastResults[]` entry and
     * flags entries whose window elapsed so the Reactive-Component can hide
     * the Undo button without a network round-trip.
     *
     * @param {Object} stateManager the state manager
     */
    tickUndoWindow(stateManager) {
        if (!stateManager.state.agent || !Array.isArray(stateManager.state.agent.lastResults)) {
            return;
        }
        stateManager.setReadOnly(false);
        stateManager.state.agent.lastResults = stateManager.state.agent.lastResults.map((entry) => {
            if (entry.undone || typeof entry.secondsLeft !== 'number') {
                return entry;
            }
            const next = entry.secondsLeft - 1;
            return {...entry, secondsLeft: Math.max(0, next), expired: next <= 0};
        });
        stateManager.setReadOnly(true);
    }

    /**
     * Resume an agent run via a second round-trip to block_ai_chat_request_ai with mode=toolagent.
     *
     * @param {Object} stateManager the state manager
     * @param {number} runid the agent run id
     */
    async resumeAgentRun(stateManager, runid) {
        this.setLoadingState(stateManager, true);
        const options = JSON.stringify({
            conversationid: stateManager.state.config.currentConversationId,
            runid,
        });
        const result = await callExternalFunctionReactiveUpdate('block_ai_chat_request_ai', {
            contextid: stateManager.state.static.contextid,
            component: stateManager.state.static.component,
            mode: 'toolagent',
            prompt: '',
            options,
        });
        this.setLoadingState(stateManager, false);
        if (result !== null) {
            stateManager.processUpdates(result);
        }
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
