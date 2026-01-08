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

import BaseContent from 'block_ai_chat/components/base_content';
import * as TinyAiUtils from 'tiny_ai/utils';
import TinyAiEditorUtils from 'tiny_ai/editor_utils';
import {constants as TinyAiConstants} from 'tiny_ai/constants';
import Templates from 'core/templates';
import {getAiConfig} from 'local_ai_manager/config';
import {getString} from 'core/str';
import {alert as displayAlert} from 'core/notification';
import {showErrorToast} from 'block_ai_chat/utils';
import {MODES} from 'block_ai_chat/constants';
import * as DomExtractor from 'block_ai_chat/dom_extractor';


class Chat extends BaseContent {
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
        this.name = 'ChatComponent';
        this.selectors = {
            MESSAGES: `[data-block_ai_chat-element='messages']`,
            INPUT_TEXTAREA: `[data-block_ai_chat-element='inputtextarea']`,
            SUBMIT_BUTTON: `[data-block_ai_chat-element='submitbutton']`,
            LOADING_SPINNER_MESSAGE: `[data-block_ai_chat-element='loadingspinner']`,
            TEMPORARY_PROMPT_MESSAGE: `[data-block_ai_chat-element='temporaryprompt']`,
            TINY_AI_BUTTON: `[data-block_ai_chat-element='tinyaibutton']`,
            OUTPUT_WRAPPER: `[data-block_ai_chat-element='outputwrapper']`,
            CHAT_OUTPUT: `[data-block_ai_chat-element='chatoutput']`,
            HISTORY_MARKER: `[data-block_ai_chat-element='historymarker']`,
        };
    }

    /**
     * Initial state ready method.
     *
     * Initially calls the setView action to set the view to 'chat' once ready to trigger the first
     * rendering of itself.
     */
    async stateReady() {
        this.reactive.dispatch('setView', 'chat');
    }

    getWatchers() {
        return [
            ...super.getWatchers(),
            {watch: `messages:created`, handler: this._addMessageToChatArea},
            {watch: `personas${this.reactive.state.config.currentPersona}:deleted`, handler: this._removeCurrentPersona},
            {watch: `config.loadingState:updated`, handler: this._handleLoadingStateUpdated},
        ];
    }

    async _addMessageToChatArea({element}) {
        let placeholder = document.createElement('div');
        placeholder.setAttribute('data-id', element.id);
        let node = this.getElement(this.selectors.CHAT_OUTPUT);
        node.appendChild(placeholder);

        const responseIsAgentResponse = element.messageMode === 'agent';

        let templateData = {
            id: element.id,
            agentMode: responseIsAgentResponse,
            senderai: element.sender === 'ai',
            loading: element.hasOwnProperty('loading') ? element.loading : false,
        };
        if (responseIsAgentResponse) {
            const agentResponse = responseIsAgentResponse ? this._getAgentAnswerTemplateContext(element.content) : {};
            templateData = {...templateData, ...agentResponse};
        } else {
            templateData.content = element.content;
        }
        const newcomponent = await this.renderComponent(placeholder, 'block_ai_chat/components/message', templateData);
        const newelement = newcomponent.getElement();
        node.replaceChild(newelement, placeholder);
        this._scrollToBottom();
        this._focusInputTextarea();
    }


    async _submitAiRequestListener() {
        const textarea = this.getElement(this.selectors.INPUT_TEXTAREA);
        const prompt = textarea.value;
        if (prompt.trim() === '') {
            const errorString = getString('erroremptyprompt', 'block_ai_chat');
            await showErrorToast(errorString);
            return;
        }
        const additionalOptions = {};
        if (this.reactive.state.config.mode === MODES.AGENT) {
            additionalOptions.agentoptions = {
                formelements: DomExtractor.extractDomElements(),
                pageid: document.body.id
            };
        }
        this.reactive.dispatch('submitAiRequest', prompt, additionalOptions);
    }

    async _handleLoadingStateUpdated({element}) {
        const loadingSpinnerMessage = {
            'id': 'loadingspinner',
            'sender': 'ai',
            'loading': true,
            'agentMode': false
        };

        const temporaryPromptMessage = {
            'id': 'temporaryprompt',
            'sender': 'user',
            'content': this.getElement(this.selectors.INPUT_TEXTAREA).value,
            'agentMode': false
        };

        if (element.loadingState) {
            await this._addMessageToChatArea({element: temporaryPromptMessage});
            await this._addMessageToChatArea({element: loadingSpinnerMessage});
            this.getElement(this.selectors.INPUT_TEXTAREA).value = '';
        }
    }

    _handleKeyDownOnInputTextarea(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this._submitAiRequestListener();
        }
    }

    _removeCurrentPersona() {
        this.reactive.dispatch('selectCurrentPersona', 0);
    }

    async _renderContent() {
        const {html, js} = await Templates.renderForPromise(
            'block_ai_chat/chat_content',
            {conversationContextLimit: this.reactive.state.config.conversationContextLimit}
        );
        Templates.replaceNodeContents(this.getElement(), html, js);
        await this._setupAfterContentRendering();
        const availabilityErrorMessage = await this.isAiChatAvailable();
        if (availabilityErrorMessage !== '') {
            const notice = await getString('notice', 'block_ai_chat');
            await displayAlert(notice, availabilityErrorMessage);
            this.setElementLocked(this.getElement(this.selectors.INPUT_TEXTAREA), true);
            this.setElementLocked(this.getElement(this.selectors.SUBMIT_BUTTON), true);
            this.getElement(this.selectors.INPUT_TEXTAREA).disabled = true;
            this.getElement(this.selectors.SUBMIT_BUTTON).disabled = true;
        }
    }

    async _setupAfterContentRendering() {
        this.reactive.dispatch('loadCurrentConversationMessages');
        const inputTextarea = this.getElement(this.selectors.INPUT_TEXTAREA);
        const sendRequestButton = this.getElement(this.selectors.SUBMIT_BUTTON);
        const tinyAiButton = this.getElement(this.selectors.TINY_AI_BUTTON);
        const uniqid = Math.random().toString(16).slice(2);

        await TinyAiUtils.init(uniqid, this.reactive.state.static.contextid, TinyAiConstants.modalModes.standalone);
        this.addEventListener(tinyAiButton, 'click', async() => {
            // We try to find selected text or images and inject it into the AI tools.
            const selectionObject = window.getSelection();
            if (selectionObject.rangeCount > 0) {
                // Safari browser does not really comply with MDN standard and sometimes has
                // rangeCount === 0. So we have to check for this to avoid running into an error.
                const range = selectionObject.getRangeAt(0);
                const container = document.createElement('div');
                container.appendChild(range.cloneContents());
                const images = container.querySelectorAll('img');
                if (images.length > 0 && images[0].src) {
                    // If there are more than one we just use the first one.
                    const image = images[0];
                    // This should work for both external and data urls.
                    const fetchResult = await fetch(image.src);
                    const data = await fetchResult.blob();
                    TinyAiUtils.getDatamanager(uniqid).setSelectionImg(data);
                }

                // If currently there is text selected we inject it.
                if (selectionObject.toString() && selectionObject.toString().length > 0) {
                    TinyAiUtils.getDatamanager(uniqid).setSelection(selectionObject.toString());
                }
            }

            const editorUtils = new TinyAiEditorUtils(
                uniqid,
                'block_ai_chat',
                this.reactive.state.static.contextid,
                this.reactive.state.static.userid,
                null
            );
            TinyAiUtils.setEditorUtils(uniqid, editorUtils);
            await editorUtils.displayDialogue();
        });

        this.addEventListener(sendRequestButton, 'click', this._submitAiRequestListener);
        this.addEventListener(inputTextarea, 'keydown', this._handleKeyDownOnInputTextarea);

        this._scrollToBottom();
        this._enableTextAreaAutoResize();
        this._focusInputTextarea();
    }

    getViewName() {
        return 'chat';
    }

    _scrollToBottom() {
        const chatOutputWrapper = this.getElement(this.selectors.OUTPUT_WRAPPER);
        chatOutputWrapper.scrollTop = chatOutputWrapper.scrollHeight;
    }

    _focusInputTextarea() {
        const inputTextarea = this.getElement(this.selectors.INPUT_TEXTAREA);
        requestAnimationFrame(() => {
            inputTextarea.focus();
        });
    }

    _enableTextAreaAutoResize() {
        const inputTextarea = this.getElement(this.selectors.INPUT_TEXTAREA);
        this.addEventListener(inputTextarea, 'keydown', () => {
            // Handle autogrow/-shrink.
            // Reset the height to auto to get the correct scrollHeight.
            inputTextarea.style.height = 'auto';

            // Fetch the computed styles.
            const computedStyles = window.getComputedStyle(inputTextarea);
            const lineHeight = parseFloat(computedStyles.lineHeight);
            const paddingTop = parseFloat(computedStyles.paddingTop);
            const paddingBottom = parseFloat(computedStyles.paddingBottom);
            const borderTop = parseFloat(computedStyles.borderTopWidth);
            const borderBottom = parseFloat(computedStyles.borderBottomWidth);

            // Calculate the maximum height for four rows plus padding and borders.
            const maxHeight = (lineHeight * 4) + paddingTop + paddingBottom + borderTop + borderBottom;

            // Calculate the new height based on the scrollHeight.
            const newHeight = Math.min(inputTextarea.scrollHeight + borderTop + borderBottom, maxHeight);

            // Set the new height.
            inputTextarea.style.height = newHeight + 'px';
        });
    }

    /**
     * Is user allowed to use the chatbot.
     *
     * @returns {string} Empty string if available, error message if not.
     */
    async isAiChatAvailable() {
        const contextid = this.reactive.state.static.contextid;
        const aiConfig = await getAiConfig(contextid, null, ['chat']);
        if (aiConfig.availability.available === 'disabled') {
            return aiConfig.availability.errormessage;
        }
        if (aiConfig.purposes[0].available === 'disabled') {
            return aiConfig.purposes[0].errormessage;
        }
        return '';
    }

    _getAgentAnswerTemplateContext(content) {
        const agentAnswer = JSON.parse(content);
        const chatOutputIntroObject = agentAnswer.chatoutput.filter(object => object.type === 'intro')[0];
        const chatOutputOutroObject = agentAnswer.chatoutput.filter(object => object.type === 'outro')[0];
        const suggestionContext = {
            intro: chatOutputIntroObject.text,
            suggestions: [],
            outro: chatOutputOutroObject.text
        };
        agentAnswer.formelements.forEach(async(formElement) => {
                const htmlElement = document.getElementById(formElement.id);
                let newValue = formElement.newValue.trim();
                if (newValue.length === 0) {
                    newValue = 0;
                }
                suggestionContext.suggestions.push({
                    fieldname: formElement.label,
                    explanation: formElement.explanation,
                    elementId: formElement.id,
                    suggestionvalue: newValue,
                    suggestiondisplayvalue: newValue,
                    disabledButtons: !htmlElement
                });
            }
        );
        return suggestionContext;
    }
}

export default Chat;
