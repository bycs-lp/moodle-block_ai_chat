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
import {renderInfoBox} from 'local_ai_manager/infobox';
import {renderWarningBox} from 'local_ai_manager/warningbox';

/**
 * Component representing a message in the ai_chat.
 */
class Boxes extends BaseComponent {

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
        this.selectors = {
            INFOBOX: `[data-block_ai_chat-element='ai_manager-infobox']`,
            WARNINGBOX: `[data-block_ai_chat-element='ai_manager-warningbox']`,
        };
        this.hideTimeout = null;
        this.HIDE_DELAY_MS = 8000;
        this.HIDE_CLASS = 'block_ai_chat-scroll-hide';
    }

    getWatchers() {
        return [
            {watch: `config.view:updated`, handler: this._showOrHideBoxes},
        ];
    }

    async stateReady() {
        await this._renderBoxes();
        this._showOrHideBoxes();
    }

    async _renderBoxes() {
        await renderInfoBox(
            'block_ai_chat',
            this.reactive.state.config.userid,
            this.getElement(this.selectors.INFOBOX),
            ['chat']
        );
        // Show AI info warning.
        await renderWarningBox(this.getElement(this.selectors.WARNINGBOX));
    }

    _showOrHideBoxes() {
        // Clear any existing timeout.
        if (this.hideTimeout) {
            clearTimeout(this.hideTimeout);
            this.hideTimeout = null;
        }

        if (this.reactive.state.config.view === 'chat') {
            // Reset visibility and remove hide animation class.
            this.element.classList.remove('d-none', this.HIDE_CLASS);

            // Start timer to auto-hide after 4 seconds.
            this.hideTimeout = setTimeout(() => {
                this.element.classList.add(this.HIDE_CLASS);
            }, this.HIDE_DELAY_MS);
        } else {
            this.element.classList.add('d-none');
        }
    }
}

export default Boxes;
