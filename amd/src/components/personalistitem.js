import {BaseComponent} from 'core/reactive';
import ModalForm from 'core_form/modalform';
import {confirm as confirmModal} from 'core/notification';
import {getString} from 'core/str';
import {PERSONA_TYPES} from 'block_ai_chat/constants';
import Templates from 'core/templates';


/**
 * Component representing the list of available personas.
 */
class PersonaListItem extends BaseComponent {
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
        this.id = parseInt(this.getElement().dataset.block_ai_chatPersonaId);
        this.selectors = {
            PERSONA_NAME_ELEMENT: `[data-block_ai_chat-element='personaname']`,
            PERSONA_LIST_ITEM: `[data-block_ai_chat-component="personalistitem"]`,
            EDIT_PERSONA_LINK: `[data-block_ai_chat-element='editpersonalink']`,
            DUPLICATE_PERSONA_LINK: `[data-block_ai_chat-element='duplicatepersonalink']`,
            DELETE_PERSONA_LINK: `[data-block_ai_chat-element='deletepersonalink']`,
        };
        this.addEventListener(this.getElement(this.selectors.PERSONA_NAME_ELEMENT), 'click', this._handleMarkPersona);
        if (this.id === 0) {
            // This is our dummy object "no persona", skip it.
            return;
        }
        this.getElement().classList.add('ml-2');
        const editPersonaLink = this.getElement(this.selectors.EDIT_PERSONA_LINK);
        if (editPersonaLink) {
            this.addEventListener(editPersonaLink, 'click', async(e) => {
                e.preventDefault();
                await this._renderPersonaEditForm();
            });
        }

        this.addEventListener(this.getElement(this.selectors.DUPLICATE_PERSONA_LINK), 'click', (e) => {
            e.preventDefault();
            this._handleDuplicatePersona();
        });

        const deletePersonaLink = this.getElement(this.selectors.DELETE_PERSONA_LINK);
        if (deletePersonaLink) {
            this.addEventListener(deletePersonaLink, 'click', async(e) => {
                e.preventDefault();
                const deletetext = await getString('delete', 'core');
                const confirmtext = await this.getDeleteWarningText();
                const canceltext = await getString('cancel', 'moodle');
                confirmModal(
                    deletetext,
                    confirmtext,
                    deletetext,
                    canceltext,
                    () => {
                        this.reactive.dispatch('deletePersona', this.id);
                    }
                );
            });
        }
    }

    stateReady(state) {
        this._applyFormatting({element: state.config});
    }

    getWatchers() {
        return [
            {watch: `config.currentlyMarkedPersona:updated`, handler: this._applyFormatting},
            {watch: `personas[${this.id}]:deleted`, handler: this.remove},
            // We rerender the whole personalist on updates, because we need to reorder etc.
            // That's why we do not listen for additional persona updates here.
        ];
    }

    /**
     * Marks this persona list item.
     */
    _handleMarkPersona() {
        this.reactive.dispatch('markPersona', this.id);
    }

    /**
     * Applies formatting to the persona list item based on whether it is selected and/or marked.
     *
     * @param {{config: object}} the changed sub object of the state (state.config in this case)
     */
    _applyFormatting({element}) {
        if (parseInt(element.currentlyMarkedPersona) === this.id) {
            this.getElement().classList.add('block_ai_chat-selected-personaitem');
        } else {
            this.getElement().classList.remove('block_ai_chat-selected-personaitem');
        }
        if (this.id === this.reactive.state.config.currentPersona) {
            this.getElement().classList.add('block_ai_chat-current-personaitem');
        } else {
            this.getElement().classList.remove('block_ai_chat-current-personaitem');
        }
    }

    _removePersonaItem() {
        this.remove();
    }

    async _renderPersonaEditForm() {
        const selectedPersona = this.reactive.state.personas.get(this.id);
        const title = await getString('editpersonatitle', 'block_ai_chat');
        const personaForm = new ModalForm({
            formClass: 'block_ai_chat\\form\\persona_form',
            moduleName: 'core/modal_save_cancel',
            args: {
                contextid: this.reactive.state.static.contextid,
                component: this.reactive.state.static.component,
                personaid: this.id,
                name: selectedPersona.name,
                prompt: selectedPersona.prompt,
                userinfo: selectedPersona.userinfo,
                userid: selectedPersona.userid,
                type: selectedPersona.type
            },
            modalConfig: {
                title
            },
            returnFocus: this.getElement()
        });
        this.addEventListener(personaForm, personaForm.events.FORM_SUBMITTED, this._personaFormSubmitted);

        // Show modal.
        personaForm.show();
    }

    _personaFormSubmitted(event) {
        this.reactive.dispatch('processDynamicFormUpdates', event.detail.content);
    }

    _handleDuplicatePersona() {
        this.reactive.dispatch('duplicatePersona', this.id);
    }

    /**
     * Generates the warning text that should be shown when deleting this persona.
     *
     * The warning text is being customized for template personas which can be used across the whole site.
     */
    async getDeleteWarningText() {
        const isTemplate = this.reactive.state.personas.get(this.id).type === PERSONA_TYPES.TYPE_TEMPLATE;
        const {html, js} = await Templates.renderForPromise('block_ai_chat/templatedelete_warning', {isTemplate});
        // Not really needed so far, but in case we need to run any JS in the future.
        Templates.runTemplateJS(js);
        return html;
    }
}

export default PersonaListItem;