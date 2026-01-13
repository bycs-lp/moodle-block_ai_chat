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
import {getString} from 'core/str';
import {stripHtmlTags} from 'block_ai_chat/utils';

/**
 * Component representing a message in the ai_chat.
 */
class Title extends BaseComponent {

    newDialogString = null;

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
        this.name = 'title';
        this.selectors = {
            TITLE: `[data-block_ai_chat-element='titleheading']`,
        };
    }

    async stateReady(state) {
        this.getElement(this.selectors.TITLE).innerText = '';
        this.newDialogString = await getString('newdialog', 'block_ai_chat');
        // Probably not necessary, because messages are loaded after the title component is ready,
        // so the watcher on messages:created will overwrite this default.
        // We keep it to make sure to have a title in case this changes.
        const title = state.messages.size > 0
            ? stripHtmlTags(state.messages.values().next().value.content)
            : this.newDialogString;
        this.getElement(this.selectors.TITLE).innerText = title;
    }

    /**
     * Watchers for this component.
     * @returns {array}
     */
    getWatchers() {
        return [
            {watch: `messages:created`, handler: this._updateTitleChat},
            {watch: `messages:deleted`, handler: this._updateTitleChat},
            {watch: `config.view:updated`, handler: this._updateTitle},
        ];
    }


    _updateTitleChat({element}) {
        if (this.reactive.state.config.view !== 'chat') {
            return;
        }
        if (this.reactive.state.messages.size === 0) {
            this.getElement(this.selectors.TITLE).innerText = this.newDialogString;
            return;
        }
        if (this.reactive.state.messages.values().next().value.id !== element.id) {
            return;
        }
        this.getElement(this.selectors.TITLE).innerText = stripHtmlTags(element.content);
    }

    async _updateTitle({element}) {
        if (element.view === 'history') {
            const historyString = await getString('history', 'block_ai_chat');
            this.getElement(this.selectors.TITLE).innerText = historyString;
        } else if (element.view === 'personalist') {
            const personalistString = await getString('managepersona', 'block_ai_chat');
            this.getElement(this.selectors.TITLE).innerText = personalistString;
        }
    }
}

export default Title;
