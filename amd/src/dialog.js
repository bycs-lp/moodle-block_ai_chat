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

import Modal from 'core/modal';
import * as externalServices from 'block_ai_chat/webservices';
import Templates from 'core/templates';
import {alert as displayAlert, exception as displayException, deleteCancelPromise,
    confirm as confirmModal} from 'core/notification';
import SaveCancelModal from 'core/modal_save_cancel';
import ModalEvents from 'core/modal_events';
import ModalForm from 'core_form/modalform';
import * as helper from 'block_ai_chat/helper';
import * as manager from 'block_ai_chat/ai_manager';
import {getString} from 'core/str';
import {renderInfoBox} from 'local_ai_manager/infobox';
import {renderUserQuota} from 'local_ai_manager/userquota';
import {renderWarningBox} from 'local_ai_manager/warningbox';
import {getAiConfig} from 'local_ai_manager/config';
import LocalStorage from 'core/localstorage';
import {escapeHTML, hash, scrollToBottom} from './helper';
import * as TinyAiUtils from 'tiny_ai/utils';
import TinyAiEditorUtils from 'tiny_ai/editor_utils';
import {constants as TinyAiConstants} from 'tiny_ai/constants';

// Declare variables.
const VIEW_CHATWINDOW = 'block_ai_chat_chatwindow';
const VIEW_OPENFULL = 'block_ai_chat_openfull';
const VIEW_DOCKRIGHT = 'block_ai_chat_dockright';
const MODAL_OPEN = 'block_ai_chat_open';

// Modal.
let modal = {};
let strHistory;
let strNewDialog;
let strToday;
let strYesterday;
let strDefinePersona;
let strNewPersona;
let strUserTemplates;
let strSystemTemplates;
let personaForm = {};
let personaPrompt = '';
let personaInfo = '';
let personaLink = '';
let personaNewname = {};
let personaButtondelete = {};
let personaUserinfo = {};
let personaInputprompt = {};
let systemTemplateHiddenInput = {};
let showPersona = false;
let optionsForm = {};
let showOptions = false;
let isAdmin = false;
let badge;
let viewmode;
let modalopen = false;

// Current conversation.
let conversation = {
    id: 0,
    messages: [],
};
// All conversations.
let allConversations = [];
// Userid.
let userid = 0;
// Block context id.
let contextid = 0;
// First load.
let firstLoad = true;
// AI in process of answering.
let aiAtWork = false;
// Maximum history included in query, should be reset via webservice.
let maxHistory = 5;
// Remember warnings for maximum history in this session.
let maxHistoryWarnings = new Set();
// Tenantconfig.
let tenantConfig = {};
let chatConfig = {};

class DialogModal extends Modal {
    static TYPE = "block_ai_chat/dialog_modal";
    static TEMPLATE = "block_ai_chat/dialog_modal";

    configure(modalConfig) {
        // Show this modal on instantiation.
        modalConfig.show = false;

        // Remove from the DOM on close.
        modalConfig.removeOnClose = false;

        modalConfig.isVerticallyCentered = false;

        super.configure(modalConfig);
    }

    hide() {
        super.hide();
        // Keep track of state, to restrict changes to block_ai_chat modal.
        modalopen = false;
        const body = document.querySelector('body');
        body.classList.remove(MODAL_OPEN);
    }
}

export const init = async(params) => {
    // Read params.
    userid = params.userid;
    contextid = params.contextid;
    strNewDialog = params.new;
    strHistory = params.history;
    strDefinePersona = params.persona;
    strNewPersona = params.newpersona;
    strUserTemplates = params.usertemplates;
    strSystemTemplates = params.systemtemplates;
    personaPrompt = params.personaprompt;
    personaInfo = params.personainfo;
    showPersona = params.showpersona;
    showOptions = params.showoptions;
    personaLink = params.personalink;
    isAdmin = params.isadmin;
    badge = params.badge;
    // Disable badge.
    badge = false;

    // Get configuration.
    const aiConfig = await getAiConfig(contextid, null, ['chat']);
    tenantConfig = aiConfig;
    chatConfig = aiConfig.purposes[0];

    // Build chat dialog modal.
    modal = await DialogModal.create({
        templateContext: {
            title: strNewDialog,
            badge: badge,
            showPersona: showPersona,
            showOptions: showOptions,
        },
    });

    // Add class for styling when modal is displayed.
    modal.getRoot().on('modal:shown', function(e) {
        e.target.classList.add("block_ai_chat_modal");
    });

    // Conditionally prevent outside click event.
    modal.getRoot().on(ModalEvents.outsideClick, event => {
        checkOutsideClick(event);
    });

    // Check and set viewmode.
    setView();

    // Attach listener to the ai button to call modal.
    let button = document.getElementById('ai_chat_button');
    button.addEventListener('mousedown', async() => {
        showModal(params);
    });

    // Get strings.
    strToday = await getString('today', 'core');
    strYesterday = await getString('yesterday', 'block_ai_chat');

    // Create a MediaQueryList object to check for small screens.
    const mediaQuery = window.matchMedia("(max-width: 576px)");

    // Attach the event listener to handle changes.
    mediaQuery.addEventListener('change', handleScreenWidthChange);

    // Initial check for screenwidth.
    if (window.innerWidth <= 576) {
        setView(VIEW_OPENFULL);
    }
};

/**
 * Show ai_chat modal.
 */
async function showModal() {
    // Switch for repeated clicking.
    if (modalopen) {
        modal.hide();
        return;
    }

    // Show modal.
    await modal.show();
    modalopen = true;
    const body = document.querySelector('body');
    body.classList.add(MODAL_OPEN);

    // Add listener for input submission.
    const textarea = document.getElementById('block_ai_chat-input-id');
    addTextareaListener(textarea);
    const button = document.getElementById('block_ai_chat-submit-id');
    button.addEventListener("click", (event) => {
        clickSubmitButton(event);
    });

    if (firstLoad) {
        // Load conversations.
        await getConversations();

        // Show conversation.
        await showConversation();

        // Get conversationcontext message limit.
        let reply = await externalServices.getConversationcontextLimit(contextid);
        maxHistory = reply.limit;

        // Add listeners for dropdownmenus.
        // Actions.
        const btnNewDialog = document.getElementById('block_ai_chat_new_dialog');
        btnNewDialog.addEventListener('click', () => {
            newDialog();
        });
        const btnDeleteDialog = document.getElementById('block_ai_chat_delete_dialog');
        btnDeleteDialog.addEventListener('click', () => {
            deleteCurrentDialog();
        });
        const btnShowHistory = document.getElementById('block_ai_chat_show_history');
        btnShowHistory.addEventListener('click', () => {
            showHistory();
        });
        const btnDefinePersona = document.getElementById('block_ai_chat_define_persona');
        if (btnDefinePersona) {
            btnDefinePersona.addEventListener('click', async() => {
                if (isAdmin) {
                    await confirmModal(
                        getString('notice', 'block_ai_chat'),
                        getString('personasystemtemplateedit', 'block_ai_chat'),
                        getString('confirm', 'core'),
                        null,
                        showPersonasModal,
                        null
                    );
                } else {
                    await showPersonasModal();
                }
            });
        }
        const btnOptions = document.getElementById('block_ai_chat_options');
        if (btnOptions) {
            btnOptions.addEventListener('click', () => {
                showOptionsModal();
            });
        }
        // Views.
        const btnChatwindow = document.getElementById(VIEW_CHATWINDOW);
        btnChatwindow.addEventListener('click', () => {
            setView(VIEW_CHATWINDOW);
        });
        const btnFullWidth = document.getElementById(VIEW_OPENFULL);
        btnFullWidth.addEventListener('click', () => {
            setView(VIEW_OPENFULL);
        });
        const btnDockRight = document.getElementById(VIEW_DOCKRIGHT);
        btnDockRight.addEventListener('click', () => {
            setView(VIEW_DOCKRIGHT);
        });

        // Show userquota.
        await renderUserQuota('#block_ai_chat_userquota', ['chat']);
        // Show infobox.
        await renderInfoBox(
            'block_ai_chat', userid, '.block_ai_chat_modal_body [data-content="local_ai_manager_infobox"]', ['chat']
        );
        // Show ai info warning.
        const warningBoxSelector = '.local_ai_manager-ai-warning';
        if (document.querySelector(warningBoxSelector)) {
            await renderWarningBox(warningBoxSelector);
        }
        // Show persona info.
        if (personaPrompt !== '') {
            showUserinfo(true);
        }

        // Check if all permissions and settings are correct.
        const message = await userAllowed();
        if (message !== '') {
            const notice = await getString('notice', 'block_ai_chat');
            await displayAlert(notice, message);
        }

        const aiUtilsButton = document.querySelector('[data-action="openaiutils"]');
        const uniqid = Math.random().toString(16).slice(2);

        await TinyAiUtils.init(uniqid, contextid, TinyAiConstants.modalModes.standalone);
        aiUtilsButton.addEventListener('click', async() => {
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

            const editorUtils = new TinyAiEditorUtils(uniqid, 'block_ai_chat', contextid, userid, null);
            TinyAiUtils.setEditorUtils(uniqid, editorUtils);
            await editorUtils.displayDialogue();
        });

        firstLoad = false;
    }

    helper.focustextarea();
}


/**
 * Webservice Get all conversations.
 */
const getConversations = async() => {
    try {
        allConversations = await externalServices.getAllConversations(userid, contextid);
    } catch (error) {
        displayException(error);
    }
};

/**
 * Function to set conversation.
 * @param {*} id
 */
const showConversation = async(id = 0) => {
    // Dissallow changing conversations when question running.
    if (aiAtWork) {
        return;
    }
    // Change conversation or get last conversation.
    if (id !== 0) {
        // Set selected conversation.
        conversation = allConversations.find(x => x.id === id);
    } else if (typeof allConversations[0] !== 'undefined') {
        // Set last conversation.
        conversation = allConversations.at(allConversations.length - 1);
    } else if (allConversations.length === 0) {
        // Last conversation has been deleted.
        newDialog(true);
    }
    clearMessages();
    setModalHeader();
    await showMessages();
};
// Make globally accessible since it is used to show history in dropdownmenuitem.mustache.
document.showConversation = showConversation;


/**
 * Send input to ai connector.
 * @param {*} question
 */
const enterQuestion = async(question) => {

    // Deny changing dialogs until answer present?
    if (question == '') {
        aiAtWork = false;
        return;
    }
    const message = await userAllowed();
    if (message !== '') {
        const notice = await getString('noticenewquestion', 'block_ai_chat');
        await displayAlert(notice, message);
        aiAtWork = false;
        return;
    }

    // Add to conversation, answer not yet available.
    showMessage(question, 'self', false);

    // For first message, add the personaprompt, even if empty.
    // Since we dont know if the personaPrompt was changed, always replace it.
    if (typeof conversation.messages[0] == 'undefined' || conversation.messages[0].sender !== 'system') {
        conversation.messages.unshift({
            'message': personaPrompt,
            'sender': 'system'
        });
    } else {
        // Replace personaPrompt.
        conversation.messages[0] = {
            'message': personaPrompt,
            'sender': 'system'
        };
    }

    // Check history for length limit.
    let convHistory = await checkMessageHistoryLengthLimit(conversation.messages);

    // Since some models cant handle an empty system message, remove from convHistory.
    if (personaPrompt.trim() === '' && convHistory[0].sender === 'system') {
        convHistory.shift();
    }
    // Options, with conversation history.
    const options = {
        'component': 'block_ai_chat',
        'conversationcontext': convHistory
    };

    // For a new conversation, get an id.
    if (conversation.id === 0) {
        try {
            let idresult = await externalServices.getNewConversationId(contextid);
            conversation.id = idresult.id;
            conversation.timecreated = Math.floor(Date.now() / 1000);
            setModalHeader(escapeHTML(question));
        } catch (error) {
            displayException(error);
        }
        options.forcenewitemid = true;
    }

    // Pass itemid / conversationid.
    options.itemid = conversation.id;

    // Send to local_ai_manager.
    let requestresult = await manager.askLocalAiManager('chat', question, contextid, options);

    // Handle errors.
    if (requestresult.code != 200) {
        requestresult = await errorHandling(requestresult, question, contextid, options);
    }

    // Attach copy listener.
    let copy = document.querySelector('.block_ai_chat_modal .awaitanswer .copy');
    copy.addEventListener('mousedown', () => {
        helper.copyToClipboard(copy);
    });

    // Write back answer.
    await showReply(requestresult.result);

    // Render mathjax.
    helper.renderMathjax();

    // Ai is done.
    aiAtWork = false;

    // Save new question and answer.
    if (requestresult.code == 200) {
        // If new conversation, sanitize the prompt for modal title and history.
        if (conversation.messages.length == 0) {
            question = escapeHTML(question);
        }
        saveConversationLocally(question, requestresult.result);
    }

    // Update userquota.
    const userquota = document.getElementById('block_ai_chat_userquota');
    userquota.innerHTML = '';
    renderUserQuota('#block_ai_chat_userquota', ['chat']);
};

/**
 * Render reply.
 * @param {string} text
 */
const showReply = async(text) => {
    // Get textblock.
    let fields = document.querySelectorAll('.block_ai_chat_modal .awaitanswer .text');
    const field = fields[fields.length - 1];
    // Render the reply.
    const {html, js} = await Templates.renderForPromise('block_ai_chat/reply', {text});
    Templates.replaceNodeContents(field, html, js);
    field.classList.remove('small');

    // Remove awaitanswer class.
    let awaitdivs = document.querySelectorAll('.block_ai_chat_modal .awaitanswer');
    const awaitdiv = awaitdivs[awaitdivs.length - 1];
    awaitdiv.classList.remove('awaitanswer');

    // Check if answer is smaller than the viewport, if so scroll to bottom.
    const container = document.querySelector('.block_ai_chat-output-wrapper');
    if (field.scrollHeight < container.clientHeight) {
        scrollToBottom();
    }
};

const showMessages = async() => {
    for (const item of conversation.messages) {
        await showMessage(item.message, item.sender);
    }
};

/**
 * Show answer from local_ai_manager.
 * @param {*} text
 * @param {*} sender User or Ai
 * @param {*} answer Is answer in history
 */
const showMessage = async(text, sender = '', answer = true) => {
    // Skip if sender is system.
    if (sender === 'system') {
        return;
    }
    // Imitate bool for message.mustache logic {{#sender}}.
    if (sender === 'ai') {
        sender = '';
    }
    // Escape chars for immediate rendering.
    if (!answer) {
        text = escapeHTML(text);
    }

    const templateData = {
        "sender": sender,
        "content": text,
        "answer": answer,
    };
    // Call the function to load and render our template.
    const {html, js} = await Templates.renderForPromise('block_ai_chat/message', templateData);
    Templates.appendNodeContents('.block_ai_chat-output', html, js);

    // Add copy listener for question and reply.
    helper.attachCopyListenerLast();

    // Scroll the modal content to the bottom.
    helper.scrollToBottom();
};

/**
 * Create new / Reset dialog.
 * @param {bool} deleted
 */
const newDialog = async(deleted = false) => {
    if (aiAtWork) {
        return;
    }
    // Add current convo local representation, if not already there.
    if (allConversations.find(x => x.id === conversation.id) === undefined && !deleted) {
        allConversations.push(conversation);
    }
    // Reset local conservation.
    conversation = {
        id: 0,
        messages: [],
    };
    clearMessages();
    setModalHeader(strNewDialog);
    helper.focustextarea();
};

/**
 * Delete /hide current dialog.
 */
const deleteCurrentDialog = () => {
    deleteCancelPromise(
        getString('delete', 'block_ai_chat'),
        getString('deletewarning', 'block_ai_chat'),
    ).then(async() => {
        if (conversation.id !== 0) {
            try {
                const deleted = await externalServices.deleteConversation(contextid, userid, conversation.id);
                if (deleted) {
                    removeFromHistory();
                    await showConversation();
                }
            } catch (error) {
                displayException(error);
            }
        }
        return;
    }).catch(() => {
        return;
    });
};

/**
 * Show conversation history.
 */
const showHistory = async() => {
    // Add current convo local representation, if not already there.
    if (allConversations.find(x => x.id === conversation.id) === undefined) {
        allConversations.push(conversation);
    }
    // Change title and add backlink.
    let title = '<a href="#" id="block_ai_chat_backlink"><i class="icon fa fa-arrow-left"></i>' + strHistory + '</a>';
    clearMessages(true);
    setModalHeader(title);
    const btnBacklink = document.getElementById('block_ai_chat_backlink');
    btnBacklink.addEventListener('click', async() => {
        if (conversation.id !== 0) {
            await showConversation(conversation.id);
        } else {
            newDialog();
            clearMessages();
        }
        setModalHeader();
    });

    // Set modal class to hide info about ratelimits and infobox.
    let modal = document.querySelector('.block_ai_chat_modal');
    modal.classList.add('onhistorypage');

    // Iterate over conversations and group by date.
    let groupedByDate = {};
    allConversations.forEach((convo) => {

        if (typeof convo.messages[0] !== 'undefined') {
            // Get first prompt for title.
            let title = '';
            if (convo.messages[0].sender == 'system') {
                title = convo.messages[1].message;
            } else {
                title = convo.messages[0].message;
            }

            // Get date and sort convos into a date array.
            const now = new Date();
            const date = new Date(convo.timecreated * 1000);
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterday = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 1);
            const twoWeeksAgo = new Date(now);
            twoWeeksAgo.setDate(now.getDate() - 14);

            const options = {weekday: 'long', day: '2-digit', month: '2-digit'};
            const monthOptions = {month: 'long', year: '2-digit'};

            // Create a date string.
            let dateString = '';
            if (date >= today) {
                dateString = strToday;
            } else if (date >= yesterday) {
                dateString = strYesterday;
            } else if (date >= twoWeeksAgo) {
                dateString = date.toLocaleDateString(undefined, options);
            } else {
                dateString = date.toLocaleDateString(undefined, monthOptions);
            }

            // Create a time string.
            const hours = date.getHours();
            const minutes = date.getMinutes().toString().padStart(2, '0');

            let convItem = {
                "title": title,
                "conversationid": convo.id,
                "time": hours + ':' + minutes,
            };

            // Save entry under the date.
            if (!groupedByDate[dateString]) {
                groupedByDate[dateString] = [];
            }
            groupedByDate[dateString].push(convItem);
        }
    });

    // Convert the grouped objects into an array format that Mustache can iterate over.
    let convert = {
        groups: Object.keys(groupedByDate).map(key => ({
            key: key,
            objects: groupedByDate[key]
        }))
    };

    // Render history.
    const templateData = {
        "dates": convert.groups,
    };
    const {html, js} = await Templates.renderForPromise('block_ai_chat/history', templateData);
    Templates.appendNodeContents('.block_ai_chat_modal .block_ai_chat-output', html, js);

    // Add a listener for the new dialog button.
    const btnNewDialog = document.getElementById('ai_chat_history_new_dialog');
    btnNewDialog.addEventListener('mousedown', () => {
        newDialog();
    });
};

/**
 * Remove currrent conversation from history.
 */
const removeFromHistory = () => {
    // Cant remove if new or not yet in history.
    if (conversation.id !== 0 && allConversations.find(x => x.id === conversation.id) !== undefined) {
        // Build new allConversations array without deleted one.
        allConversations = allConversations.filter(obj => obj.id !== conversation.id);
    }
};

/**
 * Webservice Save conversation.
 * @param {*} question
 * @param {*} reply
 */
const saveConversationLocally = (question, reply) => {
    // Add to local representation.
    let message = {'message': question, 'sender': 'user'};
    conversation.messages.push(message);
    message = {'message': reply, 'sender': 'ai'};
    conversation.messages.push(message);
};

/**
 * Clear output div.
 * @param {*} hideinput
 */
const clearMessages = (hideinput = false) => {
    const output = document.querySelector('.block_ai_chat-output');
    output.innerHTML = '';
    // For showing history.
    let input = document.querySelector('.block_ai_chat-input');
    if (hideinput) {
        input.style.display = 'none';
    } else {
        input.style.display = 'flex';
    }
};

/**
 * Set modal header title.
 * @param {*} setTitle
 */
const setModalHeader = (setTitle = '') => {
    let modalheader = document.querySelector('.block_ai_chat_modal .modal-title div');
    let title = '';
    if (modalheader !== null && (conversation.messages.length > 0 || setTitle.length)) {
        if (!setTitle.length) {
            if (conversation.messages[0].sender == 'system') {
                title = conversation.messages[1].message;
            } else {
                title = conversation.messages[0].message;
            }
        } else {
            title = setTitle;
        }
        modalheader.innerHTML = title;
    }
    // Remove onhistorypage, since history page is setting it.
    let modal = document.querySelector('.block_ai_chat_modal');
    modal.classList.remove('onhistorypage');
};

/**
 * Attach event listener.
 * @param {*} textarea
 */
const addTextareaListener = (textarea) => {
    textarea.addEventListener('keydown', (event) => {
        // Handle submission.
        textareaOnKeydown(event);

        // Handle autgrow.
        // Reset the height to auto to get the correct scrollHeight.
        textarea.style.height = 'auto';

        // Fetch the computed styles.
        const computedStyles = window.getComputedStyle(textarea);
        const lineHeight = parseFloat(computedStyles.lineHeight);
        const paddingTop = parseFloat(computedStyles.paddingTop);
        const paddingBottom = parseFloat(computedStyles.paddingBottom);
        const borderTop = parseFloat(computedStyles.borderTopWidth);
        const borderBottom = parseFloat(computedStyles.borderBottomWidth);

        // Calculate the maximum height for four rows plus padding and borders.
        const maxHeight = (lineHeight * 4) + paddingTop + paddingBottom + borderTop + borderBottom;

        // Calculate the new height based on the scrollHeight.
        const newHeight = Math.min(textarea.scrollHeight + borderTop + borderBottom, maxHeight);

        // Set the new height.
        textarea.style.height = newHeight + 'px';
    });
};

/**
 * Action for textarea submission.
 * @param {*} event
 */
const textareaOnKeydown = (event) => {
    if (event.key === 'Enter' && !aiAtWork && !event.shiftKey) {
        aiAtWork = true;
        enterQuestion(event.target.value);
        event.preventDefault();
        event.target.value = '';
    }
};

/**
 * Submit form.
 */
const clickSubmitButton = () => {
    // Var aiAtWork to make it impossible to submit multiple questions at once.
    if (!aiAtWork) {
        aiAtWork = true;
        const textarea = document.getElementById('block_ai_chat-input-id');
        enterQuestion(textarea.value);
        textarea.value = '';
    }
};

/**
 * Handle error from local_ai_manager.
 * @param {*} requestresult
 * @param {*} question
 * @param {*} contextid
 * @param {*} options
 * @returns {object}
 */
const errorHandling = async(requestresult, question, contextid, options) => {

    // If code 409, conversationid is already taken, try get new a one.
    if (requestresult.code == 409) {
        while (requestresult.code == 409) {
            try {
                let idresult = await externalServices.getNewConversationId(contextid);
                conversation.id = idresult.id;
                options.itemid = conversation.id;
            } catch (error) {
                displayException(error);
            }
            // Retry with new id.
            requestresult = await manager.askLocalAiManager('chat', question, contextid, options);
            return requestresult;
        }
    }

    // If any other errorcode, alert with errormessage.
    const errorString = await getString('errorwithcode', 'block_ai_chat', requestresult.code);
    const result = JSON.parse(requestresult.result);
    await displayAlert(errorString, result.message);

    // Change answer styling to differentiate from ai.
    const answerdivs = document.querySelectorAll('.awaitanswer');
    const answerdiv = answerdivs[answerdivs.length - 1];
    const messagediv = answerdiv.closest('.message');
    messagediv.classList.add('text-danger');

    // And write generic error message in chatbot.
    requestresult.result = await getString('error', 'block_ai_chat');
    return requestresult;
};

/**
 * Check historic messages for max length.
 * @param {array} messages
 * @returns {array}
 */
const checkMessageHistoryLengthLimit = async(messages) => {
    const length = messages.length;
    if (length > maxHistory) {
        // Cut history.
        let shortenedMessages = [messages[0], ...messages.slice(-maxHistory)];

        // Show warning once per session.
        if (!maxHistoryWarnings.has(conversation.id)) {
            const maxHistoryString = await getString('maxhistory', 'block_ai_chat', maxHistory);
            const warningErrorString = await getString('maxhistoryreached', 'block_ai_chat', maxHistory);
            await displayAlert(maxHistoryString, warningErrorString);
            // Remember warning.
            maxHistoryWarnings.add(conversation.id);
        }
        return shortenedMessages;
    }
    // Limit not reached, return messages.
    return messages;
};

/**
 * Check if modal should close on outside click.
 * @param {*} event
 */
const checkOutsideClick = (event) => {
    // View openfull acts like a normal modal.
    if (viewmode != VIEW_OPENFULL) {
        event.preventDefault();
    }
};

/**
 * Set different viewmodes and save in local storage.
 * @param {string} mode
 */
const setView = async(mode = '') => {
    const key = await hash('chatmode' + userid);
    // Check for saved viewmode.
    let savedmode = LocalStorage.get(key);
    if (mode == '') {
        if (!savedmode) {
            // Set default.
            mode = VIEW_CHATWINDOW;
        } else {
            mode = savedmode;
        }
    }
    // Save viewmode and set global var.
    LocalStorage.set(key, mode);
    viewmode = mode;

    // Set viewmode as bodyclass.
    const body = document.querySelector('body');
    body.classList.remove(VIEW_CHATWINDOW, VIEW_OPENFULL, VIEW_DOCKRIGHT);
    body.classList.add(mode);
};

/**
 * Is user allowed new queries.
 * @returns {message}
 */
const userAllowed = async() => {
    if (tenantConfig.availability.available === 'disabled') {
        return tenantConfig.availability.errormessage;
    }
    if (chatConfig.available === 'disabled') {
        return chatConfig.errormessage;
    }
    return '';
};

/**
 * Change to openfull view when screen is small.
 * @param {*} e
 */
const handleScreenWidthChange = (e) => {
    const body = document.querySelector('body');
    if (e.matches) {
        // Screen width is less than 576px
        body.classList.remove(VIEW_CHATWINDOW, VIEW_OPENFULL, VIEW_DOCKRIGHT);
        body.classList.add(VIEW_OPENFULL);
    } else {
        body.classList.remove(VIEW_CHATWINDOW, VIEW_OPENFULL, VIEW_DOCKRIGHT);
        body.classList.add(viewmode);
    }
};

/**
 * Show personas modal.
 */
const showPersonasModal = () => {
    // Add a dynamic form to add a systemprompt/persona to a block instance.
    // Always create the dynamic form modal, since it is being destroyed.
    personaForm = new ModalForm({
        formClass: "block_ai_chat\\form\\persona_form",
        moduleName: "block_ai_chat/modal_save_delete_cancel",
        args: {
            contextid: contextid,
        },
        modalConfig: {
            title: strDefinePersona,
        },
    });

    // Show modal.
    personaForm.show();

    // If select[template] is changed, change textarea[prompt].
    // For this, we want to get the value of the hidden input with name="prompts".
    // So we wait for the modalForm() to be LOADED to get the modal object.
    // On the modal object we wait for the bodyRendered event to read the input.
    personaForm.addEventListener(personaForm.events.LOADED, () => {
        personaForm.modal.getRoot().on(ModalEvents.bodyRendered, () => {
            const inputprompts = document.querySelector('input[name="prompts"]');
            const prompts = JSON.parse(inputprompts.value);
            const select = document.querySelector('select[name="template"]');
            const addpersona = document.querySelector('#add_persona');
            const copypersona = document.querySelector('#copy_persona');
            personaNewname = document.querySelector('input[name="name"]');
            personaInputprompt = document.querySelector('textarea[name="prompt"]');
            personaUserinfo = document.querySelector('textarea[name="userinfo"]');
            const inputtemplateids = document.querySelector('input[name="templateids"]');
            const templateids = JSON.parse(inputtemplateids.value);
            const inputuserinfos = document.querySelector('input[name="userinfos"]');
            const userinfos = JSON.parse(inputuserinfos.value);
            personaButtondelete = document.querySelector('[data-custom="delete"]');
            systemTemplateHiddenInput = document.querySelector('[data-type="systemtemplate"]');

            personaNewname.value = personaNewname.value.trim();

            // Sort personal templates to the end, so we can make two categories in the dropdown.
            select.options.forEach((option) => {
                if (!templateids.map(id => parseInt(id)).includes(parseInt(option.value)) && parseInt(option.value) !== 0) {
                    select.options[select.options.length - 1].after(option);
                }
            });

            // Disable delete/name on system templates.
            manageInputs(false, templateids, select.value);

            // Now we can add a listener to reflect select[template] to textarea[prompt].
            select.addEventListener('change', (event) => {
                let selectValue = event.target.value;
                let selectText = event.target.options[select.selectedIndex].text.trim();
                // Enable all.
                manageInputs(true);

                // Reflect prompt, name and userinfos.
                if (typeof prompts[selectValue] !== 'undefined') {
                    personaInputprompt.value = prompts[selectValue];
                    // For personaNewname, get_formdata needs setAttribute,
                    // but .value is used to repopulate after placeholder is used.
                    personaNewname.value = selectText;
                    personaNewname.setAttribute('placeholder', '');
                    personaNewname.setAttribute('value', selectText);
                    personaUserinfo.value = userinfos[selectValue];
                    personaUserinfo.disabled = false;
                    personaInputprompt.disabled = false;
                } else {
                    // Should be selection "No Persona"
                    personaNewname.value = '';
                    personaNewname.setAttribute('value', '');
                    personaInputprompt.value = '';
                    personaInputprompt.disabled = true;
                    personaUserinfo.value = '';
                    personaUserinfo.disabled = true;
                }
                // Disable delete/name on system templates.
                manageInputs(false, templateids, selectValue);
            });

            // Remove newpersona signifier option on click.
            select.addEventListener('click', () => {
                let option = document.querySelector('.new-persona-placeholder');
                if (option) {
                    select.removeChild(option);
                }
            });

            // Add headlines and spacing to the template select element.
            // But before adding options make a comparison to check for usertemplates.
            const useroptions = select.options.length > templateids.length;
            // Add spacing after "No persona".
            const spacer = new Option('', '', false, false);
            spacer.disabled = true;
            spacer.classList.add('select-spacer');
            select.insertBefore(spacer, select.options[1]);
            // Add systemtemplates heading.
            const systemtemplates = new Option(strSystemTemplates, '', false, false);
            systemtemplates.disabled = true;
            select.insertBefore(systemtemplates, select.options[2]);
            // // Add usertemplates heading.
            if (useroptions) {
                let lastSystemOption;
                if (templateids.length === 0) {
                    lastSystemOption = systemtemplates;
                } else {
                    // Get last systemtemplate position.
                    const maxValue = Math.max(...templateids.map(id => parseInt(id)));
                    lastSystemOption = Array.from(select.options).find(opt => parseInt(opt.value) === maxValue);
                }
                // Add heading.
                const usertemplates = new Option(strUserTemplates, '', false, false);
                usertemplates.disabled = true;
                select.insertBefore(usertemplates, lastSystemOption.nextSibling);
            }

            // Add listener to addPersona icon.
            addpersona.addEventListener('click', () => {
                addPersona(false, select);
            });

            // Add listener to copyPersona icon.
            copypersona.addEventListener('click', () => {
                addPersona(true, select);
            });

            // To use process_dynamic_submission() for deletion, we use a save button but add a delete hidden input.
            // Make sure it is set 1 on deletion and to 0 on actual saving process.
            const actionbuttons = document.querySelectorAll('[data-action="save"]');
            actionbuttons.forEach((button) => {
                button.addEventListener('click', async(e) => {
                    const deleteinput = document.querySelector('input[name="delete"]');
                    if (e.target.dataset.custom == 'delete') {
                        deleteinput.value = '1';
                        if (e.target.dataset.confirmed !== "1") {
                            e.stopPropagation();
                            await confirmModal(getString('delete', 'core'),
                                getString('areyousuredelete', 'block_ai_chat'),
                                getString('delete', 'core'),
                                null,
                                () => {
                                    e.target.dataset.confirmed = "1";
                                    e.target.click();
                                },
                                null
                            );
                        }
                    } else {
                        deleteinput.value = '0';
                        if (select.value == "" && isAdmin && e.target.dataset.confirmed !== "1") {
                            e.stopPropagation();
                            const modal = await SaveCancelModal.create({
                                title: getString('systemorpersonal_title', 'block_ai_chat'),
                                body: getString('systemorpersonal_question', 'block_ai_chat'),
                                buttons: {
                                    save: getString('systemtemplate', 'block_ai_chat'),
                                    cancel: getString('personaltemplate', 'block_ai_chat')
                                },
                                removeOnClose: true,
                                show: true
                            });
                            modal.getRoot().on(ModalEvents.save,
                                () => {
                                    systemTemplateHiddenInput.value = 1;
                                    e.target.dataset.confirmed = "1";
                                    e.target.click();
                                }
                            );
                            modal.getRoot().on(ModalEvents.cancel,
                                () => {
                                    systemTemplateHiddenInput.value = 0;
                                    e.target.dataset.confirmed = "1";
                                    e.target.click();
                                }
                            );
                        }
                    }
                });
            });
        });
    });

    // Enable admintemplate name input on save.
    personaForm.addEventListener(personaForm.events.SUBMIT_BUTTON_PRESSED, () => {
        manageInputs(true);
    });


    // Also enable admintemplate name input on error.
    personaForm.addEventListener(personaForm.events.SERVER_VALIDATION_ERROR, () => {
        manageInputs(true);
    });

    // Reload persona and rewrite info on submission.
    personaForm.addEventListener(personaForm.events.FORM_SUBMITTED, async() => {
        let reply = await externalServices.reloadPersona(contextid);
        personaPrompt = reply.prompt;
        personaInfo = reply.info;
        showUserinfo(false);
    });
};


/**
 * Show options modal.
 */
const showOptionsModal = () => {
    // Add a dynamic form to add options.
    // Always create the dynamic form modal, since it is being destroyed.
    optionsForm = new ModalForm({
        formClass: "block_ai_chat\\form\\options_form",
        moduleName: "core/modal_save_cancel",
        args: {
            contextid: contextid,
        },
        modalConfig: {
            title: getString('options'),
        },
    });

    // Show modal.
    optionsForm.show();

    // Reload options on submission.
    optionsForm.addEventListener(optionsForm.events.FORM_SUBMITTED, async() => {
        let reply = await externalServices.getConversationcontextLimit(contextid);
        maxHistory = reply.limit;
    });
};

/**
 * Click on add new persona, make input writable and reset if no copy.
 * @param {bool} copy
 * @param {HTMLElement} select
 */
const addPersona = (copy, select) => {
    // Enable inputs and set a placeholder.
    personaNewname.disabled = false;
    personaNewname.placeholder = strNewPersona;
    personaNewname.value = '';
    personaInputprompt.disabled = false;
    personaUserinfo.disabled = false;
    if (!copy) {
        personaInputprompt.value = '';
        personaUserinfo.value = '';
    }
    // Add option to signify new persona.
    let option = document.querySelector('.new-persona-placeholder');
    if (!option) {
        let signifierOption = new Option(strNewPersona, '', true, true);
        signifierOption.classList.add('new-persona-placeholder');
        select.add(signifierOption);
    }
};

const manageInputs = (switchon, templateids = [], selectValue = 42) => {
    // Switch all inputs on.
    if ((switchon || isAdmin) && selectValue != 0) {
        // Enable everything except for "No persona".
        personaNewname.disabled = false;
        personaButtondelete.disabled = false;
        personaInputprompt.disabled = false;
        personaUserinfo.disabled = false;
        return;
    }
    // Abort on reload if validation failed.
    if (document.querySelector('.is-invalid') !== null) {
        return;
    }
    // Switch input between admin and user templates.
    if (templateids.includes(selectValue) || selectValue == 0) {
        personaNewname.disabled = true;
        personaButtondelete.disabled = true;
        personaInputprompt.disabled = true;
        personaUserinfo.disabled = true;
    } else {
        personaNewname.disabled = false;
        personaButtondelete.disabled = false;
        personaInputprompt.disabled = false;
        personaUserinfo.disabled = false;
    }
};

const showUserinfo = async(first) => {
    // If persona is updated, delete current infobox.
    if (!first) {
        const toDelete = document.querySelector('.local_ai_manager-infobox.alert.alert-info');
        if (toDelete) {
            toDelete.remove();
        }
    }

    if (personaInfo.trim() != '') {
        const targetElement = document.querySelector('.block_ai_chat_modal_body .infobox');
        const templateContext = {
            'persona': personaInfo,
            'personainfourl': personaLink,
        };
        const {html, js} = await Templates.renderForPromise('block_ai_chat/persona_infobox', templateContext);
        Templates.appendNodeContents(targetElement, html, js);
    }
};
