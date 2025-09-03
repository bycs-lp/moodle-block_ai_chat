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

/**
 * Module providing functions to extract information from the form.
 *
 * @module     block_ai_chat/agent_buttons
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as DomExtractor from 'block_ai_chat/dom_extractor';

export const init = () => {
    document.querySelectorAll('[data-block_ai_chat-action="accept_suggestion"]').forEach(button => {
        console.log(button)
        button.addEventListener('click', (e) => {
            console.log(button.dataset);
            button.disabled = true;
            const declineButton = document.querySelector('[data-block_ai_chat-action="decline_suggestion"][data-block_ai_chat-for-element="' + button.dataset.block_ai_chatForElement + '"]');
            declineButton.disabled = true;
            injectSuggestionIntoForm(button);
        })
    });

    document.querySelectorAll('[data-block_ai_chat-action="decline_suggestion"]').forEach(button => {
        console.log(button)
        button.addEventListener('click', (e) => {
            console.log(button.dataset);
            button.disabled = true;
            const acceptButton = document.querySelector('[data-block_ai_chat-action="accept_suggestion"][data-block_ai_chat-for-element="' + button.dataset.block_ai_chatForElement + '"]');
            acceptButton.disabled = true;
        })
    });

    document.querySelectorAll('[data-block_ai_chat-action="target_suggestion"]').forEach(button => {
        console.log(button)
        button.addEventListener('click', (e) => {
            console.log(button.dataset);
            targetFieldInView(button);
        })
    });

};

const injectSuggestionIntoForm = (button) => {
    const htmlElement = document.getElementById(button.dataset.block_ai_chatForElement);
    console.log("NOW INJECTING")
    console.log("VALUE TO INJECT")
    console.log(button.dataset.block_ai_chatSuggestionvalue)
    console.log(htmlElement)
    htmlElement.value = button.dataset.block_ai_chatSuggestionvalue;
    const formElements = DomExtractor.extractDomElements();
    const relatedElement = formElements.filter(formElement => formElement.id === button.dataset.block_ai_chatForElement)[0];
    console.log(relatedElement)
    if (relatedElement.type === 'textarea') {
        const tiny = window.tinymce.get(relatedElement.id);
        if (tiny) {
            tiny.setContent(button.dataset.block_ai_chatSuggestionvalue);
        }
    } else if (relatedElement.type === 'checkbox') {
        console.log('its a checkbox')
        if (parseInt(button.dataset.block_ai_chatSuggestionvalue) === 1) {
            console.log("setting the check")
            htmlElement.checked = true;
        } else {
            console.log("deleteing the check")
            delete htmlElement.checked;
        }
        console.log(button.dataset.block_ai_chatSuggestionvalue)
    }
    console.log('html element after manipulation')
    console.log(htmlElement);
}

const targetFieldInView = (button) => {
    const htmlElement = document.getElementById(button.dataset.block_ai_chatForElement);
    console.log("TARGETING FIELD");
    console.log(htmlElement);

    if (!htmlElement) {
        console.warn('Target element not found:', button.dataset.block_ai_chatForElement);
        return;
    }

    // Scroll element into view with smooth behavior and center it
    htmlElement.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
        inline: 'nearest'
    });

    // Focus the element if it's focusable
    if (htmlElement.focus && typeof htmlElement.focus === 'function') {
        // Small delay to ensure scroll is complete
        setTimeout(() => {
            htmlElement.focus();
        }, 500);
    }
}
