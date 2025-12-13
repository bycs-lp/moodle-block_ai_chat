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
import Templates from 'core/templates';
import {hash} from 'block_ai_chat/utils';
import LocalStorage from 'core/localstorage';
import ModalEvents from 'core/modal_events';
import {RENDER_MODE} from 'block_ai_chat/constants';

class Main extends BaseComponent {

    create(descriptor) {
        this.modal = descriptor.modal;
        this.name = 'MainComponent';

        this.classes = {
            WINDOWMODE_WINDOW: 'block_ai_chat_chatwindow',
            WINDOWMODE_OPENFULL: 'block_ai_chat_openfull',
            WINDOWMODE_DOCKRIGHT: 'block_ai_chat_dockright'
        };
        this.selectors = {
            CONTENTAREA: `[data-block_ai_chat-component='contentarea']`,
            TITLEAREA_PLACEHOLDER: `[data-block_ai_chat-element='titleareaplaceholder']`
        };
    }

    /**
     * Called, as soon as the state is ready and the component has been fully registered.
     *
     * It will render all sub components.
     *
     * @param {object} state the initial state
     */
    async stateReady(state) {
        await this._renderSubComponents();

        if (state.static.renderMode === RENDER_MODE.MODAL) {
            this.getElement().closest('.modal').classList.add('block_ai_chat_modal');
            // Attach listener to the AI button to call modal.
            // We cannot use the reactive addEventListener here because the button is outside this component.
            const button = document.getElementById('ai_chat_button');
            button.addEventListener('mousedown', async() => {
                this.reactive.dispatch('setModalVisibility');
            });
            // Listening to the 'hidden' event here to fire the mutation is actually pretty bad design.
            // It should be the other way round that altering the state triggers the modal hide/show.
            // However, because the modal has also other methods to be closed (ESC key, own close button
            // outside the control of our reactive component) we have to make sure that these external
            // close events at least are reflected in our state.
            this.modal.getRoot().on(ModalEvents.hidden, () => {
                if (state.config.modalVisible) {
                    this.reactive.dispatch('setModalVisibility', false);
                }
            });
            // Prevent modal to be closed when clicking outside.
            this.modal.getRoot().on(ModalEvents.outsideClick, event => {
                event.preventDefault();
            });
            let storedWindowMode = await this._getLastWindowModeFromLocalStorage();
            if (storedWindowMode === 'window' && window.innerWidth <= 576) {
                storedWindowMode = 'openfull';
            }
            this.reactive.dispatch('setWindowMode', storedWindowMode);
            this.bodyObserver = this._setupOverflowWatcher();
            // Initially show modal.
            this.reactive.dispatch('setModalVisibility', true);
        }

        // Add a toast region to show toast messages when clicking the copy button
        // of the messages.
        // Unfortunately, we cannot use "addToastRegion" from 'core/toast', because this would
        // inject a toast region at the top of the page, not the top of our modal, so we have to
        // render our own toast wrapper.
        const {html, js} = await Templates.renderForPromise('block_ai_chat/toast_wrapper', {});
        Templates.prependNodeContents(this.getElement(this.selectors.CONTENTAREA), html, js);
    }

    destroy() {
        if (this.bodyObserver) {
            this.bodyObserver.disconnect();
        }
    }

    getWatchers() {
        return [
            {watch: `config.windowMode:updated`, handler: this._handleWindowModeUpdated},
            {watch: `config.windowMode:updated`, handler: this._storeWindowModeToLocalStorage},
            {watch: `config.modalVisible:updated`, handler: this._toggleModalVisibility},
        ];
    }

    async _handleWindowModeUpdated({element}) {
        const body = document.querySelector('body');
        Array.from(Object.values(this.classes)).forEach((classname) => {
            body.classList.remove(classname);
        });
        let windowModeClass = null;
        switch (element.windowMode) {
            case 'window':
                windowModeClass = this.classes.WINDOWMODE_WINDOW;
                break;
            case 'openfull':
                windowModeClass = this.classes.WINDOWMODE_OPENFULL;
                break;
            case 'dockright':
                windowModeClass = this.classes.WINDOWMODE_DOCKRIGHT;
                break;
        }
        body.classList.add(windowModeClass);
    }

    async _getLastWindowModeFromLocalStorage() {
        const key = await hash('chatmode' + this.reactive.state.static.userid);
        // Check for saved WINDOWMODE.
        const currentValue = LocalStorage.get(key);
        return currentValue ? currentValue : 'window';
    }

    async _storeWindowModeToLocalStorage() {
        const key = await hash('chatmode' + this.reactive.state.static.userid);
        LocalStorage.set(key, this.reactive.state.config.windowMode);
    }

    async _toggleModalVisibility({element}) {
        if (this.reactive.state.static.renderMode === RENDER_MODE.EMBEDDED) {
            return;
        }
        if (element.modalVisible) {
            await this.modal.show();
            document.body.classList.add('block_ai_chat_open');
        } else {
            await this.modal.hide();
            document.body.classList.remove('block_ai_chat_open');
        }
    }

    /**
     * Keeps the body overflow in check while the chat modal is open.
     *
     * This is pretty ugly, but: Whenever a bootstrap modal is being rendered the overflow style of the body
     * will be set to "hidden" to prevent scrolling of the background. However, in our case we want to
     * allow scrolling of the background while the chat modal is open. Therefore, we need to watch for
     * changes of the body style attribute and remove the overflow hidden if our chat modal is open. We cannot do this
     * in reaction to the modal open/close events, because this happens for each(!) modal, so also notification modals,
     * confirm modals, dynamic forms etc.
     *
     * @returns {MutationObserver} the mutation observer object
     */
    _setupOverflowWatcher() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                    const body = document.body;
                    const computedStyle = window.getComputedStyle(body);

                    if (computedStyle.overflow === 'hidden' && this.reactive.state.config.modalVisible) {
                        document.body.style.removeProperty('overflow');
                        document.body.style.removeProperty('padding-right');
                    }
                }
            });
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['style']
        });
        return observer;
    }

    /**
     * Renders the subcomponents of the main component.
     */
    async _renderSubComponents() {
        // Title area is not a component itself, so we cannot use this.renderComponent here.
        const titleAreaPlaceholder = document.createElement('div');
        this.getElement(this.selectors.TITLEAREA_PLACEHOLDER).appendChild(titleAreaPlaceholder);
        const titleAreaComponent = await this.renderComponent(titleAreaPlaceholder, 'block_ai_chat/components/titlearea', {
            showPersona: this.reactive.state.static.showPersona,
            showOptions: this.reactive.state.static.showOptions,
            showViews: this.reactive.state.static.renderMode === RENDER_MODE.MODAL
        });
        this.getElement(this.selectors.TITLEAREA_PLACEHOLDER).replaceWith(titleAreaComponent.getElement());
        titleAreaPlaceholder.remove();

        const placeholderChatarea = document.createElement('div');
        this.getElement(this.selectors.CONTENTAREA).appendChild(placeholderChatarea);
        const chatComponent = await this.renderComponent(placeholderChatarea, 'block_ai_chat/components/chat', {});
        this.getElement(this.selectors.CONTENTAREA).appendChild(chatComponent.getElement());
        placeholderChatarea.remove();

        const placeholderHistory = document.createElement('div');
        this.getElement(this.selectors.CONTENTAREA).appendChild(placeholderHistory);
        const historyComponent = await this.renderComponent(placeholderHistory, 'block_ai_chat/components/history', {});
        this.getElement(this.selectors.CONTENTAREA).appendChild(historyComponent.getElement());
        placeholderHistory.remove();

        const placeholderPersonalist = document.createElement('div');
        this.getElement(this.selectors.CONTENTAREA).appendChild(placeholderPersonalist);
        const personaListComponent = await this.renderComponent(placeholderPersonalist, 'block_ai_chat/components/personalist', {});
        this.getElement(this.selectors.CONTENTAREA).appendChild(personaListComponent.getElement());
        placeholderPersonalist.remove();
    }
}

export default Main;
