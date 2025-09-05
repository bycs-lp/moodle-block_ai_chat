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
import {renderUserQuota} from 'local_ai_manager/userquota';

/**
 * Component representing a message in the ai_chat.
 */
class UserQuota extends BaseComponent {

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
        this.name = 'userquota';
    }

    async stateReady(state) {
        await this._rerenderUserQuota({element: state.config});
    }

    /**
     * Watchers for this component.
     * @returns {array}
     */
    getWatchers() {
        return [
            {watch: `config.loadingState:updated`, handler: this._rerenderUserQuota.bind(this)},
            {watch: `config.view:updated`, handler: this._rerenderUserQuota.bind(this)},
        ];
    }

    async _rerenderUserQuota({element}) {
        if (!element.loadingState) {
            // Only refetch information if loading has finished.
            await renderUserQuota(this.getElement(), ['chat', 'agent']);
        }
    }
}

export default UserQuota;
