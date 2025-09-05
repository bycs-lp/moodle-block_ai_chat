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
import {events} from 'local_ai_manager/events';
import {debounce} from 'core/utils';

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
            // CARE: This is outside of this component's root element.
            MODAL_CONTENT: `.modal-content`,
            // CARE: This is outside of this component's root element.
            OUTPUT_WRAPPER: `[data-block_ai_chat-element='outputwrapper']`,
        };
        this._debouncedCollapseBoxes = debounce(this._collapseBoxes.bind(this), 250);
    }

    getWatchers() {
        return [
            {watch: `config.view:updated`, handler: this._showOrHideBoxes},
            {watch: `messages.rendered:updated`, handler: this._debouncedCollapseBoxes},
            {watch: `messages:deleted`, handler: this._debouncedCollapseBoxes},
        ];
    }

    async stateReady() {
        await this._renderBoxes();
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
        if (this.reactive.state.config.view === 'chat') {
            this.getElement(this.selectors.INFOBOX).classList.remove('d-none');
            this.getElement(this.selectors.WARNINGBOX).classList.remove('d-none');
        } else {
            this.getElement(this.selectors.INFOBOX).classList.add('d-none');
            this.getElement(this.selectors.WARNINGBOX).classList.add('d-none');
        }
    }

    _collapseBoxes() {
        const chatOutputWrapper = this.getElement().closest(this.selectors.MODAL_CONTENT)
            .querySelector(this.selectors.OUTPUT_WRAPPER);
        const hasVisibleScrollbar = chatOutputWrapper.scrollHeight > chatOutputWrapper.clientHeight;
        const infoboxTargetElement = this.getElement(this.selectors.INFOBOX);
        const warningboxTargetElement = this.getElement(this.selectors.WARNINGBOX);
        if (hasVisibleScrollbar) {
            infoboxTargetElement.dispatchEvent(new CustomEvent(events.collapseInfoBox));
            warningboxTargetElement.dispatchEvent(new CustomEvent(events.collapseWarningBox));
        } else {
            infoboxTargetElement.dispatchEvent(new CustomEvent(events.maximizeInfoBox));
            warningboxTargetElement.dispatchEvent(new CustomEvent(events.maximizeWarningBox));
        }
    }
}

export default Boxes;
