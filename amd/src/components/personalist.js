import BaseContent from 'block_ai_chat/components/base_content';
import {getString} from 'core/str';
import {PERSONA_TYPES} from 'block_ai_chat/constants';
import Templates from 'core/templates';


/**
 * Component representing the list of available personas.
 */
class PersonaList extends BaseContent {

    static init(target) {
        let element = document.querySelector(target);
        return new this({
            element: element,
        });
    }

    create() {
        this.selectors = {
            PERSONA_LIST_ITEM_SELECTED: `[data-block_ai_chat-element="personalistitem"].block_ai_chat-selected-personaitem`,
            PERSONALIST_CONTENT: `[data-block_ai_chat-component="personalist-content"]`,
            ADD_NEW_PERSONA_BUTTON: `[data-block_ai_chat-element="addnewpersonabutton"]`,
            BACK_TO_CHAT_BUTTON: `[data-block_ai_chat-element='backtochatbutton']`,
            SELECT_PERSONA_BUTTON: `[data-block_ai_chat-element='selectpersonabutton']`
        };
    }

    stateReady() {
        this._hideContent();
    }

    /**
     * Watchers for this component.
     * @returns {array}
     */
    getWatchers() {
        return [
            ...super.getWatchers(),
            {watch: `personas:created`, handler: this._personaCreated},
            {watch: `personas:updated`, handler: this._renderContent},
        ];
    }

    async _renderContent() {
        const nopersonaString = await getString('nopersona', 'block_ai_chat');
        const templateContext = {
            generalpersonas: [
                {
                    id: 0,
                    name: nopersonaString,
                    nopersona: true
                }
            ],
            templatepersonas: [],
            userpersonas: []
        };
        // Sort alphabetically by name.
        const personas = Array.from(this.reactive.state.personas.values());
        personas.sort((a, b) => a.name.localeCompare(b.name));
        personas.forEach((persona) => {
            const personaTemplateObject = this._exportPersonaForTemplate(persona);
            if (parseInt(persona.type) === PERSONA_TYPES.TYPE_TEMPLATE) {
                templateContext.templatepersonas.push(personaTemplateObject);
            } else if (parseInt(persona.type) === PERSONA_TYPES.TYPE_USER) {
                templateContext.userpersonas.push(personaTemplateObject);
            }
        });

        const {html, js} = await Templates.renderForPromise('block_ai_chat/personalist_content', templateContext);
        Templates.replaceNodeContents(this.getElement(), html, js);

        await this._setupAfterContentRendering();
    }

    _handleSelectPersona() {
        this.reactive.dispatch('selectCurrentPersonaAndLoadChat', this.reactive.state.config.currentlyMarkedPersona);
    }

    _handleAddNewPersona() {
        this.reactive.dispatch('createNewDummyPersona');
    }

    async _personaCreated({element}) {
        let placeholder = document.createElement('div');
        placeholder.setAttribute('data-block_ai_chat-persona-id', element.id);
        this.getElement(this.selectors.PERSONALIST_CONTENT).appendChild(placeholder);
        const newcomponent = await this.renderComponent(
            placeholder,
            'block_ai_chat/components/personalistitem',
            this._exportPersonaForTemplate(this.reactive.state.personas.get(element.id))
        );
        const newelement = newcomponent.getElement();
        this.getElement(this.selectors.PERSONALIST_CONTENT).replaceChild(newelement, placeholder);
    }

    _handleBackToChat() {
        this.reactive.dispatch('setView', 'chat');
    }

    _setupAfterContentRendering() {
        this.addEventListener(this.getElement(this.selectors.ADD_NEW_PERSONA_BUTTON),
            'click',
            this._handleAddNewPersona
        );
        this.addEventListener(this.getElement(this.selectors.BACK_TO_CHAT_BUTTON),
            'click',
            this._handleBackToChat
        );
        this.addEventListener(this.getElement(this.selectors.SELECT_PERSONA_BUTTON),
            'click',
            this._handleSelectPersona
        );
    }

    _exportPersonaForTemplate(persona) {
        return {
            id: persona.id,
            name: persona.name,
            nopersona: false,
            editable:
                this.reactive.state.static.isAdmin
                ||
                (
                    this.reactive.state.static.canEditSystemPersonas
                    && parseInt(persona.type) === PERSONA_TYPES.TYPE_TEMPLATE
                )
                ||
                (
                    parseInt(persona.type) === PERSONA_TYPES.TYPE_USER
                    && parseInt(this.reactive.state.static.userid) === parseInt(persona.userid)
                ),
        };
    }

    getViewName() {
        return 'personalist';
    }
}

export default PersonaList;
