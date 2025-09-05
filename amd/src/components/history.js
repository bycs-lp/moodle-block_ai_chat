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
import {getString} from 'core/str';
import Templates from 'core/templates';
import {callExternalFunction} from 'block_ai_chat/utils';

/**
 * History component.
 */
class History extends BaseContent {

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
        this.selectors = {
            NEW_DIALOG_BUTTON: `[data-block_ai_chat-element='newdialogbutton']`,
            CONVERSATIONLIST_ITEM: `[data-block_ai_chat-element='conversationlistitem']`,
        };
    }

    stateReady() {
        this._hideContent();
    }

    _newDialogListener() {
        this.reactive.dispatch('createAndViewNewConversation');
    }

    async _renderContent() {
        // Iterate over conversations and group by date.
        let groupedByDate = {};
        const conversationlist = await callExternalFunction('block_ai_chat_get_all_conversations', {
            contextid: this.reactive.state.static.contextid
        });
        if (conversationlist === null) {
            return;
        }

        const strToday = await getString('today', 'core');
        const strYesterday = await getString('yesterday', 'block_ai_chat');
        conversationlist.forEach((convo) => {

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
                title: convo.title,
                conversationid: convo.conversationid,
                time: hours + ':' + minutes,
            };

            // Save entry under the date.
            if (!groupedByDate[dateString]) {
                groupedByDate[dateString] = [];
            }
            groupedByDate[dateString].push(convItem);
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
            dates: convert.groups,
        };
        const {html, js} = await Templates.renderForPromise('block_ai_chat/history_content', templateData);
        Templates.replaceNodeContents(this.getElement(), html, js);
        await this._setupafterContentRendering();
    }

    _setupafterContentRendering() {
        this.addEventListener(
            this.getElement(this.selectors.NEW_DIALOG_BUTTON),
            'click',
            this._newDialogListener
        );
        this.getElement().querySelectorAll(this.selectors.CONVERSATIONLIST_ITEM).forEach((conversationlistItem) => {
            this.addEventListener(conversationlistItem, 'click', () => {
                const conversationid = conversationlistItem.dataset.block_ai_chatConversationid;
                this.reactive.dispatch('setConversationAndLoadChat', conversationid);
            });
        });
    }

    getViewName() {
        return 'history';
    }
}

export default History;