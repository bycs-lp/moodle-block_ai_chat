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
        button.addEventListener('click', () => {
            button.disabled = true;
            const declineButton = document.querySelector(
                '[data-block_ai_chat-action="decline_suggestion"][data-block_ai_chat-for-element="'
                + button.dataset.block_ai_chatForElement + '"]'
            );
            declineButton.disabled = true;
            injectSuggestionIntoForm(button);
        });
    });

    document.querySelectorAll('[data-block_ai_chat-action="decline_suggestion"]').forEach(button => {
        button.addEventListener('click', () => {
            button.disabled = true;
            const acceptButton = document.querySelector(
                '[data-block_ai_chat-action="accept_suggestion"][data-block_ai_chat-for-element="'
                + button.dataset.block_ai_chatForElement + '"]'
            );
            acceptButton.disabled = true;
        });
    });

    document.querySelectorAll('[data-block_ai_chat-action="target_suggestion"]').forEach(button => {
        button.addEventListener('click', () => {
            targetFieldInView(button);
        });
    });
};

const injectSuggestionIntoForm = (button) => {
    targetFieldInView(button, 'insert');
    const htmlElement = document.getElementById(button.dataset.block_ai_chatForElement);

    const formElements = DomExtractor.extractDomElements();
    const relatedElement = formElements.filter(formElement => formElement.id === button.dataset.block_ai_chatForElement)[0];

    if (relatedElement.type === 'textarea') {
        htmlElement.value = button.dataset.block_ai_chatSuggestionvalue;
        const tiny = window.tinymce.get(relatedElement.id);
        if (tiny) {
            tiny.setContent(button.dataset.block_ai_chatSuggestionvalue);
        }
    } else if (relatedElement.type === 'checkbox') {
        if (parseInt(button.dataset.block_ai_chatSuggestionvalue) === 1) {
            // We cannot set the value, because it won't fire events. So the mform YUI won't recognize a change.
            if (!htmlElement.checked) {
                htmlElement.click();
            }
        } else {
            if (htmlElement.checked) {
                htmlElement.click();
            }
        }
    } else if (relatedElement.type === 'select') {
        htmlElement.value = button.dataset.block_ai_chatSuggestionvalue;

        // Trigger change event to ensure any dependent JavaScript is executed
        const changeEvent = new Event('change', {bubbles: true});
        htmlElement.dispatchEvent(changeEvent);
    } else {
        // For all other input types (text, email, number, etc.)
        htmlElement.value = button.dataset.block_ai_chatSuggestionvalue;
    }
};

const targetFieldInView = (button, action) => {
    const expandAllLink = document.querySelector('.mform .collapsible-actions .collapseexpand.collapsed');
    if (expandAllLink) {
        expandAllLink.click();
    }

    requestAnimationFrame(() => {
        const htmlElement = document.getElementById(button.dataset.block_ai_chatForElement);

        if (!htmlElement) {
            return;
        }

        // Scroll element into view with smooth behavior and center it
        htmlElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center',
            inline: 'nearest'
        });

        highlightElement(htmlElement, action);

        // Focus the element if it's focusable
        requestAnimationFrame(() => {
            htmlElement.focus();
        });
    });
};

const highlightElement = (element, action) => {
    let parent = element.parentElement;
    while (parent.tagName !== 'DIV' || !parent.classList.contains('fitem')) {
        parent = parent.parentElement;
    }

    parent.classList.add('block_ai_chat-form_element_highlight');
    if (action === 'insert') {
        parent.classList.add('block_ai_chat-form_element_highlight-insert');
    }
    setTimeout(() => {
        parent.classList.remove('block_ai_chat-form_element_highlight');
        parent.classList.remove('block_ai_chat-form_element_highlight-insert');
    }, 2000);
};
