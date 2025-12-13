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
import 'core/copy_to_clipboard';

/**
 * Component representing a message in the ai_chat.
 */
export default class extends BaseComponent {

    static init(target) {
        let element = document.querySelector(target);
        return new this({
            element: element,
        });
    }

    create() {
        this.id = this.element.dataset.block_ai_chatMessageid;
        this.selectors = {
            COPY_BUTTON: `[data-block_ai_chat-element='copybutton']`,
            COPIED_TOAST: `[data-block_ai_chat-element='copiedtoast']`,
            MESSAGE_CONTENT: `[data-block_ai_chat-element='messagecontent']`,
        };
    }

    getWatchers() {
        return [
            {watch: `config.loadingState:updated`, handler: this._removeTemporaryMessage},
            {watch: `messages[${this.id}]:deleted`, handler: this.remove},
        ];
    }

    /**
     * Removes itself if it's a temporary message.
     *
     * To reflect the loading/generating of AI responses, we show temporary messages (temporaryprompt
     * and loading spinner). Once loading is complete, these messages have to remove themselves again.
     *
     * @param {{config: object}} the changed sub object of the state (state.config in this case)
     */
    _removeTemporaryMessage({element}) {
        if (element.loadingState) {
            return;
        }
        if (this.id !== 'temporaryprompt' && this.id !== 'loadingspinner') {
            return;
        }
        this.remove();
    }
}
