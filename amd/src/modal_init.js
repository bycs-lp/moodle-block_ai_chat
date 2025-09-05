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
import * as ReactiveInit from 'block_ai_chat/reactive_init';


/**
 * Initializes the AI chat modal.
 *
 * It also calls the initialization of the reactive main component which then will take care of
 * everything else.
 *
 * @param {int} contextid the context id of the used context
 */
export const init = async(contextid) => {
    document.querySelector('#ai_chat_button').addEventListener('click', async() => {
            const modal = await Modal.create({
                template: 'block_ai_chat/modal',
                show: false,
                removeOnClose: false,
                isVerticallyCentered: false
            });
            // We need to manually attach the modal to the DOM, because the reactive UI main component
            // needs an element which already is present in the DOM.
            modal.attachToDOM();
            // Initializes the reactive UI.
            await ReactiveInit.init(contextid, '.block_ai_chat_reactive_main_component', modal);
        },
        // We only want to attach the listener once, because this only does our initial spin up.
        // After that the main component (block_ai_chat/components/main) which is created in the ReactiveInit
        // handles the click on the button.
        // However, we do not want the main component to do this initialization immediately, because
        // we do not want to load all the information directly on site load, but only on first button click
        // due to performance reasons.
        {once: true}
    );
};
