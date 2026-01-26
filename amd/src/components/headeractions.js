import {BaseComponent} from 'core/reactive';
import {getString} from 'core/str';
import {confirm as confirmModal} from 'core/notification';
import ModalForm from 'core_form/modalform';
import ModalCancel from 'core/modal_cancel';
import {MODES} from 'block_ai_chat/constants';

/**
 * Component representing a message in the ai_chat.
 */
class HeaderActions extends BaseComponent {

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
        this.name = 'headeractions';
        this.selectors = {
            NEW_CONVERSATION_BUTTON: '[data-block_ai_chat-element="newconversationbutton"]',
            SHOW_HISTORY_BUTTON: '[data-block_ai_chat-element="showhistorybutton"]',
            PERSONA_LIST_BUTTON: '[data-block_ai_chat-element="personalistbutton"]',
            OPTIONS_BUTTON: '[data-block_ai_chat-element="optionsbutton"]',
            DELETE_CONVERSATION_BUTTON: '[data-block_ai_chat-element="deleteconversationbutton"]',
            VIEWMODE_WINDOW_BUTTON: '[data-block_ai_chat-element="viewmodewindowbutton"]',
            VIEWMODE_OPENFULL_BUTTON: '[data-block_ai_chat-element="viewmodeopenfullbutton"]',
            VIEWMODE_DOCKRIGHT_BUTTON: '[data-block_ai_chat-element="viewmodedockrightbutton"]',
            MODE_SWITCH: `[data-block_ai_chat-element='modeswitch']`,
            PERSONA_BANNER: `[data-block_ai_chat-element='personabanner']`,
            PERSONA_INFO_MODAL_MANAGE_PERSONA_BUTTON: `[data-block_ai_chat-element='personainfomodalpersonalistbutton']`,
            MFORM: `form.mform`,
        };
    }

    async stateReady(state) {
        await this._modeUpdated({element: state.config});
        this._refreshPersona({element: state.config});

        this.addEventListener(
            this.getElement(this.selectors.NEW_CONVERSATION_BUTTON),
            'click',
            this._newConversationListener
        );
        this.addEventListener(
            this.getElement(this.selectors.SHOW_HISTORY_BUTTON),
            'click',
            this._showHistoryListener
        );
        const personaListButton = this.getElement(this.selectors.PERSONA_LIST_BUTTON);
        if (personaListButton) {
            this.addEventListener(
                this.getElement(this.selectors.PERSONA_LIST_BUTTON),
                'click',
                this._showPersonalistListener
            );
        }
        this.addEventListener(
            this.getElement(this.selectors.PERSONA_BANNER),
            'click',
            this._showPersonaInfoModal
        );

        const modeSwitch = this.getElement(this.selectors.MODE_SWITCH);
        if (modeSwitch) {
            this.addEventListener(
                this.getElement(this.selectors.MODE_SWITCH),
                'click',
                this._clickModeSwitchListener
            );
        }

        const optionsButton = this.getElement(this.selectors.OPTIONS_BUTTON);
        if (optionsButton) {
            this.addEventListener(
                this.getElement(this.selectors.OPTIONS_BUTTON),
                'click',
                this._renderOptionsForm
            );
        }
        this.addEventListener(
            this.getElement(this.selectors.DELETE_CONVERSATION_BUTTON),
            'click',
            this._deleteConversationListener
        );

        const viewModeWindowButton = this.getElement(this.selectors.VIEWMODE_WINDOW_BUTTON);
        if (viewModeWindowButton) {
            this.addEventListener(
                this.getElement(this.selectors.VIEWMODE_WINDOW_BUTTON),
                'click',
                this._setWindowModeListener.bind(this, 'window')
            );
        }
        const viewModeOpenFullButton = this.getElement(this.selectors.VIEWMODE_OPENFULL_BUTTON);
        if (viewModeOpenFullButton) {
            this.addEventListener(
                this.getElement(this.selectors.VIEWMODE_OPENFULL_BUTTON),
                'click',
                this._setWindowModeListener.bind(this, 'openfull')
            );
        }
        const viewModeDockRightButton = this.getElement(this.selectors.VIEWMODE_DOCKRIGHT_BUTTON);
        if (viewModeDockRightButton) {
            this.addEventListener(
                this.getElement(this.selectors.VIEWMODE_DOCKRIGHT_BUTTON),
                'click',
                this._setWindowModeListener.bind(this, 'dockright')
            );
        }
    }

    /**
     * Watchers for this component.
     * @returns {array}
     */
    getWatchers() {
        return [
            // We update if we switch personas.
            {watch: `config.mode:updated`, handler: this._modeUpdated},
            {watch: `config.currentPersona:updated`, handler: this._refreshPersona},
            // We also have to update if the persona information changes.
            {watch: `personas:updated`, handler: this._personaInformationChanged},
        ];
    }


    _selectCurrentPersonaListener(event) {
        event.preventDefault();
        this.reactive.dispatch('selectCurrentPersona', this.reactive.state.static.contextid, event.target.value);
    }

    _newConversationListener(event) {
        event.preventDefault();
        this.reactive.dispatch('createAndViewNewConversation');
    }

    _showHistoryListener(event) {
        event.preventDefault();
        this.reactive.dispatch('setView', 'history');
    }

    _showPersonalistListener(event) {
        event.preventDefault();
        this.reactive.dispatch('setView', 'personalist');
    }

    async _renderOptionsForm() {
        const optionsFormModalTitle = await getString('preferences', 'block_ai_chat');
        const optionsForm = new ModalForm({
            formClass: "block_ai_chat\\form\\options_form",
            moduleName: "core/modal_save_cancel",
            args: {
                contextid: this.reactive.state.static.contextid,
            },
            modalConfig: {
                title: optionsFormModalTitle,
            },
        });

        optionsForm.show();
        this.addEventListener(optionsForm, optionsForm.events.FORM_SUBMITTED, this._optionsSubmitted);
    }

    async _deleteConversationListener(event) {
        event.preventDefault();
        const deletetext = await getString('delete', 'core');
        const confirmtext = await getString('deletewarning', 'block_ai_chat');
        const canceltext = await getString('cancel', 'moodle');
        confirmModal(
            deletetext,
            confirmtext,
            deletetext,
            canceltext,
            () => {
                this.reactive.dispatch('deleteCurrentConversation');
            }
        );
    }

    _setWindowModeListener(windowMode) {
        this.reactive.dispatch('setWindowMode', windowMode);
    }

    /**
     * We will trigger that method any time a person data changes. This method is used by stateReady
     * but, most important, to watch the state. Any watcher receive an object with:
     * - element: the afected element (a person in this case)
     * - state: the full state object
     *
     * @param {object} param the watcher param.
     * @param {object} param.element the person structure.
     */
    _refreshPersona({element}) {
        // We have a convenience method to locate elements inside the component.
        const newPersonaId = parseInt(element.currentPersona);
        const personaBanner = this.getElement(this.selectors.PERSONA_BANNER);
        const currentMode = this.reactive.state.config.mode;
        if (newPersonaId === 0 || currentMode === MODES.AGENT) {
            personaBanner.classList.add('d-none');
        } else {
            personaBanner.classList.remove('d-none');
            personaBanner.innerHTML = `${this.reactive.state.personas.get(newPersonaId).userinfo}`;
        }
    }

    _personaInformationChanged({element}) {
        if (parseInt(this.reactive.state.config.currentPersona) !== element.id) {
            return;
        }
        this._refreshPersona({element: this.reactive.state.config});
    }

    _optionsSubmitted(event) {
        this.reactive.dispatch('processDynamicFormUpdates', event.detail);
    }

    async _showPersonaInfoModal() {
        const currentPersonaId = parseInt(this.reactive.state.config.currentPersona);

        const personalink = this.reactive.state.static.personalink;
        const personalinkAvailable = personalink !== null;
        const templateContext = {
            personaSelected: currentPersonaId !== 0,
            personalinkAvailable
        };
        if (personalinkAvailable) {
            templateContext.personalink = personalink;
        }
        if (currentPersonaId !== 0) {
            const currentPersona = this.reactive.state.personas.get(currentPersonaId);
            templateContext.personaName = currentPersona.name;
            templateContext.personaUserInfo = currentPersona.userinfo;
            templateContext.personaPrompt = currentPersona.prompt;
        }
        templateContext.showPersona = this.reactive.state.static.showPersona;

        const personaInfoModal = await ModalCancel.create(
            {
                large: false,
                template: 'block_ai_chat/persona_info_modal',
                templateContext
            }
        );
        await personaInfoModal.show();
        const managePersonasButton =
            personaInfoModal.getModal()[0].querySelector(this.selectors.PERSONA_INFO_MODAL_MANAGE_PERSONA_BUTTON);
        if (managePersonasButton) {
            managePersonasButton.addEventListener('click', (event) => {
                event.preventDefault();
                this.reactive.dispatch('setView', 'personalist');
                personaInfoModal.hide();
            });
        }
    }

    async _modeUpdated({element}) {
        const modeChatString = await getString('modechat', 'block_ai_chat');
        const modeAgentString = await getString('modeagent', 'block_ai_chat');
        const modeSwitch = this.getElement(this.selectors.MODE_SWITCH);
        if (!modeSwitch) {
            // We have no mode switch, so nothing to do.
            return;
        }
        const mformElement = document.querySelector(this.selectors.MFORM);
        if (
            // TODO this can be improved, for example does not really work for dynamically hidden
            //  forms like the one mod/forum/view.php.
            mformElement
            && window.getComputedStyle(mformElement).display !== 'none'
            && window.getComputedStyle(mformElement).visibility !== 'hidden'
        ) {
            modeSwitch.classList.remove('d-none');
        } else {
            modeSwitch.classList.add('d-none');
            this.reactive.dispatch('setMode', MODES.CHAT);
        }

        modeSwitch.innerText = element.mode === MODES.AGENT ? modeAgentString : modeChatString;
        if (element.mode === MODES.AGENT) {
            modeSwitch.classList.add('bg-primary');
            modeSwitch.classList.add('text-secondary');
            modeSwitch.classList.remove('bg-secondary');
            modeSwitch.classList.remove('text-primary');
        } else {
            modeSwitch.classList.remove('bg-primary');
            modeSwitch.classList.remove('text-secondary');
            modeSwitch.classList.add('bg-secondary');
            modeSwitch.classList.add('text-primary');
        }
        // We need to check if we need to show or hide the persona banner.
        this._refreshPersona({element: this.reactive.state.config});
    }

    _clickModeSwitchListener() {
        const currentMode = this.reactive.state.config.mode;
        const newMode = currentMode === MODES.AGENT ? MODES.CHAT : MODES.AGENT;
        this.reactive.dispatch('setMode', newMode);
    }
}

export default HeaderActions;
