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
 * Extract DOM elements and export them as JSON.
 *
 * @module     block_ai_chat/dom_extractor
 * @copyright  2025 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Get DOM elements inside #page-content and return as JSON.
 *
 * This function scans the page content for form elements and extracts their properties,
 * including values, labels, help text, error messages, and complex dependencies between elements.
 * It supports all major form input types and analyzes their relationships.
 * Additionally, it extracts page context information from h2 headings with help content as additional elements.
 *
 * @returns {Array} Array containing structured form element data with dependencies and page context elements
 */
export const extractDomElements = () => {
    const elements = [];

    // Find the main content container - all Moodle forms are within #page-content.
    const contentDiv = document.getElementById('page-content');
    if (!contentDiv) {
        // Return empty structure if no content area is found.
        return [];
    }

    // Comprehensive selector for all form input types including hidden fields and buttons.
    // This covers standard inputs, textareas, selects, and special Moodle form elements.
    const selector =
        'input[type="text"], input[type="password"], input[type="email"], input[type="number"], ' +
        'input[type="search"], input[type="tel"], input[type="url"], input[type="date"], ' +
        'input[type="datetime-local"], input[type="checkbox"], input[type="radio"], input[type="file"], ' +
        'input[type="hidden"], input[type="submit"], input[type="button"], textarea, select';

    const domNodes = contentDiv.querySelectorAll(selector);

    // Process each form element found in the DOM.
    // eslint-disable-next-line complexity
    domNodes.forEach((node) => {
        let type = node.tagName.toLowerCase();
        let currentValue = '';
        const options = [];

        // Extract current value based on element type and special handling for different inputs.
        if (type === 'input') {
            type = node.type;
            if (type === 'checkbox' || type === 'radio') {
                // For checkboxes and radios, return the value only if checked, otherwise empty.
                currentValue = node.checked ? node.value || 'on' : '';
            } else {
                // For all other input types, get the current value.
                currentValue = node.value || '';
            }
        } else if (type === 'textarea') {
            type = 'textarea';

            // Special handling for TinyMCE editors which are hidden textareas with iframe content.
            if (node.style.display === 'none' && node.id) {
                const tinyFrame = document.querySelector(`#${node.id}_ifr`);
                if (tinyFrame?.contentDocument) {
                    try {
                        // Extract content from TinyMCE iframe body.
                        const tinyBody = tinyFrame.contentDocument.body;
                        currentValue = tinyBody ? (tinyBody.textContent || tinyBody.innerText || '') : node.value || '';
                    } catch (e) {
                        // Fallback to textarea value if iframe access fails (cross-origin issues).
                        currentValue = node.value || '';
                    }
                } else {
                    currentValue = node.value || '';
                }
            } else {
                // Standard textarea handling.
                currentValue = node.value || '';
            }
        } else if (type === 'select') {
            type = 'select';

            // Get selected value from select element.
            currentValue = node.selectedOptions?.length > 0 ? node.selectedOptions[0].value : (node.value || '');

            // Check if this select is within a date_time container to avoid verbose output.
            if (!isWithinDateTimeContainer(node)) {
                const optionElements = node.querySelectorAll('option');
                optionElements.forEach((option) => {
                    options.push({
                        value: option.value,
                        text: option.textContent || option.innerText || '',
                        selected: option.selected
                    });
                });
            }
        }

        // Find the associated label text using multiple strategies for Moodle form conventions.
        const label = findLabelForElement(node);

        // Extract help text/description that provides additional context for the field.
        const helptext = findHelptextForElement(node);

        // Extract error text/validation messages for the field.
        const errorText = findErrorForElement(node);

        // Determine if the element is currently active/enabled (1) or disabled (0).
        let active = 1;
        let isDisabled = false;
        let dependsOnEnabled = null;

        // Check element disabled state using multiple methods to ensure compatibility.
        // Method 1: Check the disabled property directly.
        if (node.disabled === true) {
            isDisabled = true;
        }

        // Method 2: Check for disabled attribute (covers cases like <select disabled> and <select disabled="disabled">).
        const attrNode = node.getAttributeNode?.('disabled');
        if (attrNode?.specified) {
            isDisabled = true;
        }

        // Check if element is hidden via CSS styles (but not for hidden input types).
        const computedStyle = window.getComputedStyle(node);
        if (computedStyle.display === 'none' && type !== 'hidden') {
            active = 0;
        } else {
            active = isDisabled ? 0 : 1;
        }

        // Determine visual visibility based on computed styles.
        const isVisuallyVisible = getElementVisualVisibility(node, computedStyle);

        // Check for Moodle-specific _enabled checkbox dependencies (legacy support).
        const enabledDependency = checkEnabledDependency(node);
        if (enabledDependency) {
            dependsOnEnabled = enabledDependency.checkboxName;
            // If the controlling _enabled checkbox is not checked, mark element as inactive.
            if (!enabledDependency.isEnabled) {
                active = 0;
            }
        }

        // Analyze all possible dependencies (select-based, checkbox-based, radio-based).
        const allDependencies = checkElementDependencies(node);

        // Analyze JavaScript-based dependencies (CSS classes, event handlers).
        const jsDependencies = analyzeJavaScriptDependencies(node);

        // Analyze relationships with sibling elements (similar names, numeric series).
        const siblingDependencies = analyzeSiblingDependencies(node);

        // Build the element data object with all extracted information.
        const elementData = {
            id: node.id || '',
            name: node.name || '',
            type: type,
            currentValue: currentValue,
            label: label,
            helptext: helptext,
            active: active,
            visible: isVisuallyVisible
        };

        // Add error text if present.
        if (errorText) {
            elementData.errorMessage = errorText;
        }

        // Add checked status for checkbox and radio elements.
        if (type === 'checkbox' || type === 'radio') {
            elementData.checked = node.checked || false;
        }

        // Add legacy _enabled dependency information if present.
        if (dependsOnEnabled) {
            elementData.dependsOnEnabled = dependsOnEnabled;
        }

        // Add comprehensive dependency analysis if any dependencies were found.
        if (allDependencies) {
            elementData.dependencies = allDependencies;
        }

        // Add JavaScript-based dependency information if detected.
        if (jsDependencies) {
            elementData.jsDependencies = jsDependencies;
        }

        // Add sibling relationship information if similar elements were found.
        if (siblingDependencies) {
            elementData.siblingDependencies = siblingDependencies;
        }

        // Add select options only for select elements (excluding verbose date/time selects).
        if (type === 'select' && options.length > 0) {
            elementData.options = options;
        }

        // Add the processed element to the collection.
        elements.push(elementData);
    });

    // Extract page context from h2 headings with help information and add as elements
    const pageContextElements = extractPageContext(contentDiv);
    elements.push(...pageContextElements);

    return elements;
};

/**
 * Extract heading text without help button content.
 *
 * @param {HTMLElement} heading - The heading element
 * @returns {string} Clean heading text
 */
const getHeadingText = (heading) => {
    const headingClone = heading.cloneNode(true);
    const helpButtonClone = headingClone.querySelector('a[data-bs-content]');
    if (helpButtonClone) {
        helpButtonClone.remove();
    }
    return (headingClone.textContent || headingClone.innerText || '').trim();
};

/**
 * Extract page context information from h2 headings with help content.
 *
 * This function searches for h2 elements that contain help buttons with Bootstrap popover
 * data-bs-content attributes and extracts the help text as additional context elements.
 * It uses the shared extractPageDock() method to get documentation links.
 *
 * @param {HTMLElement} contentDiv - The content container to search within
 * @returns {Array} Array of page context elements in the same format as form elements
 */
const extractPageContext = (contentDiv) => {
    const contextElements = [];

    // Find all h2 elements that might contain help information
    const headings = contentDiv.querySelectorAll('h2');

    headings.forEach((heading) => {
        // Look for help buttons with Bootstrap popover data within the heading
        const helpButton = heading.querySelector('a[data-bs-content]');

        if (helpButton && helpButton.getAttribute('data-bs-content')) {
            // Extract the heading text (without the help button content)
            const headingText = getHeadingText(heading);

            // Extract help content from the popover data attribute
            const rawHelp = helpButton.getAttribute('data-bs-content');
            let helpText = '';

            if (rawHelp) {
                // Clean HTML content from the popover to get plain text
                const tmpDiv = document.createElement('div');
                tmpDiv.innerHTML = rawHelp;
                helpText = (tmpDiv.textContent || tmpDiv.innerText || '').trim();
            }

            // Only add if we have both heading and help text
            if (headingText && helpText) {
                // Create element in the same format as form elements
                const contextElement = {
                    id: heading.id || '',
                    name: 'page_context_' + (heading.id || 'heading_' + contextElements.length),
                    type: 'page_context',
                    currentValue: '',
                    label: headingText,
                    helptext: helpText,
                    active: 1,
                    visible: 1
                };

                contextElements.push(contextElement);
            }
        }
    });

    return contextElements;
};

/**
 * Find label text for a given form element.
 *
 * This function implements multiple strategies to locate label text for form elements,
 * following Moodle's form conventions and standard HTML practices.
 *
 * @param {HTMLElement} element - The form element to find a label for
 * @returns {string} The label text, or empty string if no label is found
 */
const findLabelForElement = (element) => {
    let label = '';

    // Strategy 1: Explicit label with for attribute pointing to element's ID.
    // This is the most reliable method when elements have proper IDs.
    if (element.id) {
        const labelElement = document.querySelector(`label[for="${element.id}"]`);
        if (labelElement) {
            label = labelElement.textContent || labelElement.innerText || '';
            label = label.trim();
        }
    }

    // Strategy 2: Parent label element that wraps the input.
    // Some forms use <label><input></label> structure instead of for/id association.
    if (!label) {
        const parentLabel = element.closest('label');
        if (parentLabel) {
            label = parentLabel.textContent || parentLabel.innerText || '';
            label = label.trim();
        }
    }

    // Strategy 3: Moodle-specific form structure with Bootstrap classes.
    // Moodle forms use .fitem containers with .col-form-label sections.
    if (!label) {
        const fitemDiv = element.closest('.fitem');
        if (fitemDiv) {
            // Look for labels in the Bootstrap form structure used by Moodle.
            const labelDiv = fitemDiv.querySelector('.col-form-label label, .col-form-label p');
            if (labelDiv) {
                label = labelDiv.textContent || labelDiv.innerText || '';
                label = label.trim();
            }
        }
    }

    // Strategy 4: Accessibility attributes as fallback.
    // Use aria-label or title attributes when no visible label is found.
    if (!label) {
        label = element.getAttribute('aria-label') || element.getAttribute('title') || '';
    }

    return label;
};

/**
 * Find helptext for a given form element.
 *
 * Searches for help text associated with form elements, typically found in
 * Bootstrap popover data attributes in Moodle forms.
 *
 * @param {HTMLElement} element - The form element to find help text for
 * @returns {string} The help text content, or empty string if none is found
 */
const findHelptextForElement = (element) => {
    let helptext = '';

    // Define containers to search in hierarchical order from specific to general.
    const searchContainers = [
        element.parentElement, // Direct parent first.
        element.closest('.fitem'), // Moodle form item container.
        element.closest('.felement'), // Moodle form element wrapper.
        element.closest('.col-md-9') // Bootstrap column containing the input.
    ];

    // Search each container for help content in Bootstrap popovers.
    for (const container of searchContainers) {
        if (container) {
            // Look for help icons with Bootstrap popover data.
            const helpAnchor = container.querySelector('a[data-bs-content]');
            if (helpAnchor?.getAttribute('data-bs-content')) {
                const rawHelp = helpAnchor.getAttribute('data-bs-content');

                // Clean HTML content from the popover to get plain text.
                const tmpDiv = document.createElement('div');
                tmpDiv.innerHTML = rawHelp;
                helptext = tmpDiv.textContent || tmpDiv.innerText || '';
                helptext = helptext.trim();
                break;
            }
        }
    }

    return helptext;
};

/**
 * Find error text for a given form element.
 *
 * Searches for error messages associated with form elements, typically found in
 * Bootstrap invalid-feedback divs or similar error containers in Moodle forms.
 *
 * @param {HTMLElement} element - The form element to find error text for
 * @returns {string} The error text content, or empty string if none is found
 */
const findErrorForElement = (element) => {
    let errorText = '';

    // Define containers to search in hierarchical order from specific to general.
    const searchContainers = [
        element.parentElement, // Direct parent first.
        element.closest('.fitem'), // Moodle form item container.
        element.closest('.felement'), // Moodle form element wrapper.
        element.closest('.col-md-9') // Bootstrap column containing the input.
    ];

    // Search each container for error content.
    for (const container of searchContainers) {
        if (container) {
            // Look for Bootstrap invalid-feedback divs.
            const errorDiv = container.querySelector('.invalid-feedback, .form-control-feedback.invalid-feedback');
            if (errorDiv && errorDiv.style.display !== 'none') {
                errorText = (errorDiv.textContent || errorDiv.innerText || '').trim();
                if (errorText) {
                    break;
                }
            }

            // Also look for general error messages in .error class.
            if (!errorText) {
                const errorSpan = container.querySelector('.error');
                if (errorSpan) {
                    errorText = (errorSpan.textContent || errorSpan.innerText || '').trim();
                    // eslint-disable-next-line max-depth
                    if (errorText) {
                        break;
                    }
                }
            }

            // Look for ARIA error messages.
            if (!errorText) {
                const ariaErrorId = element.getAttribute('aria-describedby');
                if (ariaErrorId) {
                    const ariaErrorElement = document.getElementById(ariaErrorId);
                    // eslint-disable-next-line max-depth
                    if (ariaErrorElement) {
                        errorText = (ariaErrorElement.textContent || ariaErrorElement.innerText || '').trim();
                        // eslint-disable-next-line max-depth
                        if (errorText) {
                            break;
                        }
                    }
                }
            }
        }
    }

    return errorText;
};

/**
 * Check if an element depends on an _enabled checkbox.
 *
 * This function implements Moodle's convention where form elements can be controlled
 * by associated _enabled checkboxes. This pattern is commonly used in assignment
 * plugins and other areas where features can be enabled/disabled.
 *
 * @param {HTMLElement} element - The form element to check for _enabled dependencies
 * @returns {Object|null} Object with checkboxName and isEnabled, or null if no dependency
 */
const checkEnabledDependency = (element) => {
    const elementName = element.name || element.id || '';

    // Define various patterns for _enabled checkbox naming conventions.
    const patterns = [
        // Direct match: element_name -> element_name_enabled.
        `${elementName}_enabled`,
        // Partial match: some_element_option -> some_element_enabled.
        elementName.replace(/_[^_]+$/, '_enabled'),
        // Group patterns: like allowsubmissionsfromdate[day] -> allowsubmissionsfromdate[enabled].
        elementName.replace(/\[[^\]]+\]$/, '[enabled]')
    ];

    // Generate additional patterns by analyzing element name structure.
    const nameParts = elementName.split('_');
    if (nameParts.length > 1) {
        // Example: assignsubmission_file_maxfiles -> assignsubmission_file_enabled.
        for (let i = nameParts.length - 1; i >= 2; i--) {
            const baseName = nameParts.slice(0, i).join('_');
            patterns.push(`${baseName}_enabled`);
        }
    }

    // Define search containers in order of specificity.
    const searchContainers = [
        element.closest('.fitem'), // Immediate form item container.
        element.closest('fieldset'), // Fieldset grouping.
        element.closest('.fcontainer'), // Form container.
        document.getElementById('page-content') // Page-wide search as fallback.
    ];

    // Search for _enabled checkboxes using generated patterns.
    for (const enabledName of patterns) {
        for (const container of searchContainers) {
            if (!container) {
                continue;
            }

            // Look for checkbox with matching name or ID.
            let enabledCheckbox = container.querySelector(
                'input[type="checkbox"][name="' + enabledName + '"], ' +
                'input[type="checkbox"][id*="' + enabledName.replace(/[[\]]/g, '_') + '"]'
            );

            if (enabledCheckbox) {
                return {
                    checkboxName: enabledName,
                    checkboxId: enabledCheckbox.id,
                    isEnabled: enabledCheckbox.checked
                };
            }
        }
    }

    // Special handling for Moodle form groups like fgroup_id_submissionplugins.
    let fitemContainer = element.closest('.fitem');
    if (fitemContainer) {
        let fgroupMatch = fitemContainer.id && fitemContainer.id.match(/^fgroup_id_(.+)$/);
        if (fgroupMatch) {
            // Search for related _enabled checkboxes within the same group.
            let groupCheckboxes = fitemContainer.querySelectorAll('input[type="checkbox"][name*="_enabled"]');
            for (let gc = 0; gc < groupCheckboxes.length; gc++) {
                let groupCheckbox = groupCheckboxes[gc];
                let checkboxName = groupCheckbox.name;

                // Check if the element name matches the checkbox pattern.
                if (elementName.indexOf(checkboxName.replace('_enabled', '')) === 0) {
                    return {
                        checkboxName: checkboxName,
                        checkboxId: groupCheckbox.id,
                        isEnabled: groupCheckbox.checked
                    };
                }
            }
        }
    }

    return null;
};

/**
 * Check if a select element is within a date_time container.
 *
 * Date/time fields in Moodle often have many select options (days, months, years, hours, minutes)
 * which would clutter the output. This function identifies such containers to exclude their options.
 *
 * @param {HTMLElement} element - The select element to check
 * @returns {boolean} True if the element is within a date_time container
 */
const isWithinDateTimeContainer = (element) => {
    // Search for parent element with data-fieldtype="date_time" attribute.
    const dateTimeContainer = element.closest('[data-fieldtype="date_time"]');
    if (dateTimeContainer) {
        return true;
    }

    // Additional search for fieldset with date_time fieldtype.
    const fieldsetContainer = element.closest('fieldset[data-fieldtype="date_time"]');
    if (fieldsetContainer) {
        return true;
    }

    // Search for div or other containers with date_time fieldtype.
    const divContainer = element.closest('div[data-fieldtype="date_time"]');
    if (divContainer) {
        return true;
    }

    return false;
};

/**
 * Check for element dependencies (select-based, checkbox-based, radio-based).
 *
 * This is the main dependency analysis function that orchestrates the detection of
 * relationships between form elements. It identifies when elements are controlled by
 * other elements (e.g., select values determining field visibility).
 *
 * @param {HTMLElement} element - The element to check for dependencies
 * @returns {Array|null} Array of dependency objects or null if no dependencies found
 */
const checkElementDependencies = (element) => {
    const form = element.closest('form');
    if (!form) {
        return null;
    }

    const elementContainer = element.closest('.fitem');
    const dependencies = [];

    // Analyze the visibility status of the current element.
    const visibility = getElementVisibility(element, elementContainer);

    // Find all potential controlling elements within the same form.
    const controllingElements = form.querySelectorAll('select, input[type="checkbox"], input[type="radio"]');

    controllingElements.forEach((controlElement) => {
        // Skip self-references and inactive controlling elements.
        if (controlElement === element ||
            controlElement.closest('[style*="display: none"]') ||
            controlElement.hasAttribute('disabled') ||
            controlElement.disabled) {
            return;
        }

        // Analyze the relationship between the current element and potential controller.
        const dependency = analyzeElementDependency(element, controlElement, visibility);
        if (dependency) {
            dependencies.push(dependency);
        }
    });

    return dependencies.length > 0 ? dependencies : null;
};

/**
 * Get element visibility status.
 *
 * Analyzes how an element is hidden or disabled, providing detailed information
 * about the method used to hide the element. This is crucial for understanding
 * dependency relationships.
 *
 * @param {HTMLElement} element - The element to check
 * @param {HTMLElement} container - The container element (.fitem)
 * @returns {Object} Visibility information including hide method and disabled state
 */
const getElementVisibility = (element, container) => {
    let isHidden = false;
    let hideMethod = 'none';

    if (container) {
        const style = container.style;
        const hiddenAttr = container.hasAttribute('hidden');
        const computedStyle = window.getComputedStyle(container);

        // Check various methods of hiding elements.
        if (style && style.display === 'none') {
            isHidden = true;
            hideMethod = 'style_display';
        } else if (hiddenAttr) {
            isHidden = true;
            hideMethod = 'hidden_attribute';
        } else if (computedStyle.display === 'none') {
            isHidden = true;
            hideMethod = 'computed_style';
        } else if (computedStyle.visibility === 'hidden') {
            isHidden = true;
            hideMethod = 'visibility_hidden';
        }
    }

    return {
        isHidden: isHidden,
        hideMethod: hideMethod,
        isDisabled: element.disabled || element.hasAttribute('disabled')
    };
};

/**
 * Analyze potential dependency between two elements.
 *
 * This function implements the core logic for detecting dependencies between elements
 * using multiple pattern recognition strategies. It examines naming conventions,
 * container relationships, and semantic associations.
 *
 * @param {HTMLElement} dependentElement - The potentially dependent element
 * @param {HTMLElement} controlElement - The potentially controlling element
 * @param {Object} visibility - Visibility status of dependent element
 * @returns {Object|null} Dependency object or null if no relationship is found
 */
const analyzeElementDependency = (dependentElement, controlElement, visibility) => {
    const controlName = controlElement.name || '';
    const controlType = controlElement.type || controlElement.tagName.toLowerCase();
    const controlValue = getControlElementValue(controlElement);

    const dependentName = dependentElement.name || '';
    const dependentId = dependentElement.id || '';

    // Apply multiple pattern recognition strategies to identify dependencies.

    // Pattern 1: Numeric suffix dependencies (e.g., primer1, instructions1 depend on preset=1).
    const numericDependency = checkNumericSuffixDependency(dependentName, dependentId, controlName, controlValue);
    if (numericDependency) {
        return createDependencyObject(controlElement, controlValue, numericDependency.requiredValue, visibility);
    }

    // Pattern 2: Semantic name dependencies (mode -> topic/story/activities).
    const semanticDependency = checkSemanticDependency(dependentName, dependentId, controlName, controlValue);
    if (semanticDependency) {
        return createDependencyObject(controlElement, controlValue, semanticDependency.requiredValue, visibility);
    }

    // Pattern 3: Container-based dependencies using data attributes.
    const containerDependency = checkContainerDependency(dependentElement, controlElement, controlValue);
    if (containerDependency) {
        return createDependencyObject(controlElement, controlValue, containerDependency.requiredValue, visibility);
    }

    // Pattern 4: Checkbox enable/disable dependencies.
    const enableDependency = checkEnableDependency(dependentName, dependentId, controlName, controlValue, controlType);
    if (enableDependency) {
        return createDependencyObject(controlElement, controlValue, enableDependency.requiredValue, visibility);
    }

    return null;
};

/**
 * Check for numeric suffix dependencies (e.g., primer1, instructions1 depend on preset=1).
 *
 * This pattern recognition function identifies dependencies where elements with numeric
 * suffixes depend on select/radio values. Common in scenarios like question presets
 * where different numbered configurations are shown based on selection.
 *
 * @param {string} dependentName - Name of dependent element
 * @param {string} dependentId - ID of dependent element
 * @param {string} controlName - Name of control element
 * @param {string} controlValue - Value of control element
 * @returns {Object|null} Dependency info or null if no pattern matches
 */
const checkNumericSuffixDependency = (dependentName, dependentId, controlName, controlValue) => {
    // Extract numeric suffix from dependent element name or ID.
    const dependentMatch = dependentName.match(/^(.+?)(\d+)$/) || dependentId.match(/^id_(.+?)(\d+)$/);
    if (!dependentMatch) {
        return null;
    }

    const baseName = dependentMatch[1];
    const number = dependentMatch[2];

    // Normalize control element name for comparison.
    const controlBaseName = controlName.replace(/^(id_)?/, '').replace(/_$/, '');

    // Define common patterns for numeric dependencies in Moodle forms.
    const commonPatterns = [
        {control: 'preset', dependents: ['primer', 'instructions', 'example', 'template', 'config']},
        {control: 'mode', dependents: ['option', 'setting', 'param', 'field']},
        {control: 'type', dependents: ['config', 'option', 'param', 'setting']},
        {control: 'category', dependents: ['subcategory', 'item', 'field']},
        {control: 'level', dependents: ['detail', 'item', 'option']}
    ];

    // Check if the control and dependent names match any known patterns.
    for (let i = 0; i < commonPatterns.length; i++) {
        const pattern = commonPatterns[i];
        if (controlBaseName === pattern.control && pattern.dependents.indexOf(baseName) !== -1) {
            // The dependency exists if the number matches the control value.
            if (number === controlValue) {
                return {requiredValue: controlValue};
            }
        }
    }

    return null;
};

/**
 * Check for semantic name dependencies.
 *
 * This function identifies dependencies based on semantic relationships between
 * element names, where certain fields are shown/hidden based on mode selections
 * or similar conceptual groupings.
 *
 * @param {string} dependentName - Name of dependent element
 * @param {string} dependentId - ID of dependent element
 * @param {string} controlName - Name of control element
 * @param {string} controlValue - Value of control element
 * @returns {Object|null} Dependency info or null if no semantic relationship found
 */
const checkSemanticDependency = (dependentName, dependentId, controlName, controlValue) => {
    // Define semantic mappings between control values and dependent field names.
    const semanticMappings = {
        'mode': {
            '1': ['topic', 'subject', 'theme'],
            '2': ['content', 'story', 'text', 'material'],
            '3': ['activities', 'courseactivities', 'course_content', 'modules']
        },
        'type': {
            'manual': ['manual_config', 'manual_settings'],
            'auto': ['auto_config', 'auto_settings'],
            'custom': ['custom_config', 'custom_settings']
        },
        'format': {
            'html': ['html_editor', 'wysiwyg'],
            'plain': ['plain_text', 'textarea'],
            'markdown': ['markdown_editor']
        }
    };

    const controlBaseName = controlName.replace(/^(id_)?/, '');
    const dependentBaseName = dependentName.replace(/^(id_)?/, '');

    // Check if the control element and dependent element match semantic patterns.
    if (semanticMappings[controlBaseName]) {
        const valueMapping = semanticMappings[controlBaseName][controlValue];
        if (valueMapping && valueMapping.indexOf(dependentBaseName) !== -1) {
            return {requiredValue: controlValue};
        }
    }

    return null;
};

/**
 * Check for container-based dependencies using data attributes.
 *
 * This function looks for explicit dependency declarations in HTML data attributes,
 * which provide a standardized way to declare element relationships in forms.
 *
 * @param {HTMLElement} dependentElement - The dependent element
 * @param {HTMLElement} controlElement - The control element
 * @param {string} controlValue - Value of control element
 * @returns {Object|null} Dependency info or null if no data attributes found
 */
const checkContainerDependency = (dependentElement, controlElement, controlValue) => {
    const container = dependentElement.closest('.fitem');
    if (!container) {
        return null;
    }

    // Check for data-depends-on attribute pointing to the control element.
    const dependsOn = container.getAttribute('data-depends-on');
    if (dependsOn === controlElement.name || dependsOn === controlElement.id) {
        const showWhen = container.getAttribute('data-show-when') || controlValue;
        return {requiredValue: showWhen};
    }

    // Check for data-conditional attribute with JSON configuration.
    const conditional = container.getAttribute('data-conditional');
    if (conditional) {
        try {
            const conditionObj = JSON.parse(conditional);
            if (conditionObj.element === controlElement.name && conditionObj.value === controlValue) {
                return {requiredValue: controlValue};
            }
        } catch (e) {
            // Silently ignore JSON parse errors to prevent script breaking.
        }
    }

    return null;
};

/**
 * Check for enable/disable checkbox dependencies.
 *
 * Identifies checkboxes that control the enabled/disabled state of other form elements.
 * This is different from visibility dependencies and focuses on form interaction state.
 *
 * @param {string} dependentName - Name of dependent element
 * @param {string} dependentId - ID of dependent element
 * @param {string} controlName - Name of control element
 * @param {string} controlValue - Value of control element
 * @param {string} controlType - Type of control element
 * @returns {Object|null} Dependency info or null if no enable pattern matches
 */
const checkEnableDependency = (dependentName, dependentId, controlName, controlValue, controlType) => {
    if (controlType !== 'checkbox') {
        return null;
    }

    // Define common patterns for enable/disable checkbox naming.
    const patterns = [
        dependentName + '_enabled',
        dependentName + '_enable',
        'enable_' + dependentName,
        dependentName.replace(/^id_/, '') + '_enabled'
    ];

    if (patterns.indexOf(controlName) !== -1) {
        return {requiredValue: '1'};
    }

    return null;
};

/**
 * Get value from control element (select, checkbox, radio).
 *
 * Extracts the current value from different types of form controls,
 * handling the specifics of each input type appropriately.
 *
 * @param {HTMLElement} element - The control element
 * @returns {string} The current value as a string
 */
const getControlElementValue = (element) => {
    const type = element.type || element.tagName.toLowerCase();

    if (type === 'checkbox' || type === 'radio') {
        // For checkboxes and radios, return '1' if checked, '0' if unchecked.
        return element.checked ? '1' : '0';
    } else if (type === 'select' || type === 'select-one' || type === 'select-multiple') {
        // For select elements, return the selected value.
        return element.value || '';
    } else {
        // For all other input types, return the value directly.
        return element.value || '';
    }
};

/**
 * Create standardized dependency object.
 *
 * This function creates a consistent structure for dependency information
 * that can be used by AI systems to understand form element relationships.
 *
 * @param {HTMLElement} controlElement - The controlling element
 * @param {string} controlValue - Current value of controlling element
 * @param {string} requiredValue - Required value for visibility/enablement
 * @param {Object} visibility - Visibility status object
 * @returns {Object} Standardized dependency object
 */
const createDependencyObject = (controlElement, controlValue, requiredValue, visibility) => {
    return {
        controllingElement: controlElement.name || '',
        controllingElementId: controlElement.id || '',
        controllingType: controlElement.type || controlElement.tagName.toLowerCase(),
        controllingValue: controlValue,
        requiredValue: requiredValue,
        isCurrentlyVisible: !visibility.isHidden && controlValue === requiredValue,
        hideMethod: visibility.hideMethod
    };
};

/**
 * Enhanced dependency analysis that also checks for common JavaScript patterns.
 *
 * This function analyzes CSS classes and JavaScript event handlers to identify
 * additional dependency patterns that may not be captured by DOM structure alone.
 *
 * @param {HTMLElement} element - The element to analyze
 * @returns {Array|null} Array of JavaScript dependency objects or null
 */
const analyzeJavaScriptDependencies = (element) => {
    const container = element.closest('.fitem');
    if (!container) {
        return null;
    }

    const dependencies = [];

    // Analyze CSS classes that indicate dependencies.
    const classes = container.className.split(' ');
    classes.forEach((cls) => {
        // Pattern: depends-on-fieldname, show-when-fieldname-value.
        if (cls.startsWith('depends-on-')) {
            const dependencyName = cls.replace('depends-on-', '');
            dependencies.push({
                type: 'css_class',
                pattern: cls,
                dependsOn: dependencyName
            });
        } else if (cls.match(/^show-when-\w+-\w+$/)) {
            const parts = cls.replace('show-when-', '').split('-');
            if (parts.length >= 2) {
                dependencies.push({
                    type: 'css_class',
                    pattern: cls,
                    dependsOn: parts[0],
                    requiredValue: parts.slice(1).join('-')
                });
            }
        }
    });

    // Prüfe Event-Handler, die auf Änderungen reagieren
    const form = element.closest('form');
    if (form) {
        const scripts = document.querySelectorAll('script');
        scripts.forEach((script) => {
            const content = script.textContent || script.innerText;
            if (content && content.includes(element.name || element.id)) {
                // Suche nach Event-Handler-Patterns
                const eventPatterns = [
                    /addEventListener\(['"]change['"],\s*function/g,
                    /\.on\(['"]change['"],\s*function/g,
                    /onchange\s*=\s*['"]?[^'"]+/g
                ];

                eventPatterns.forEach((pattern) => {
                    if (content.match(pattern)) {
                        dependencies.push({
                            type: 'javascript_event',
                            pattern: 'change_listener',
                            element: element.name || element.id
                        });
                    }
                });
            }
        });
    }

    return dependencies.length > 0 ? dependencies : null;
};

/**
 * Analyze sibling and parent-child dependencies within form groups.
 *
 * @param {HTMLElement} element - The element to analyze
 * @returns {Object|null} Sibling dependency information
 */
const analyzeSiblingDependencies = (element) => {
    const container = element.closest('.fitem');
    if (!container) {
        return null;
    }

    const dependencies = [];
    const elementName = element.name || '';

    // Finde Geschwister-Container
    const siblingContainers = [];
    const parent = container.parentNode;
    if (parent) {
        const allFitems = parent.querySelectorAll('.fitem');
        allFitems.forEach((fitem) => {
            if (fitem !== container) {
                siblingContainers.push(fitem);
            }
        });
    }

    // Analysiere Geschwister auf ähnliche Namen
    siblingContainers.forEach((siblingContainer) => {
        const siblingElements = siblingContainer.querySelectorAll('input, select, textarea');
        siblingElements.forEach((siblingElement) => {
            const siblingName = siblingElement.name || '';

            // Pattern: Ähnliche Namen mit Suffixen/Präfixen
            const similarity = analyzeSimilarity(elementName, siblingName);
            if (similarity.score > 0.7 && similarity.type) {
                const siblingVisibility = getElementVisibility(siblingElement, siblingContainer);

                dependencies.push({
                    type: 'sibling_dependency',
                    siblingElement: siblingName,
                    siblingId: siblingElement.id || '',
                    similarity: similarity,
                    siblingVisible: !siblingVisibility.isHidden
                });
            }
        });
    });

    return dependencies.length > 0 ? dependencies : null;
};

/**
 * Analyze similarity between two element names.
 *
 * @param {string} name1 - First element name
 * @param {string} name2 - Second element name
 * @returns {Object} Similarity analysis
 */
const analyzeSimilarity = (name1, name2) => {
    if (!name1 || !name2) {
        return {score: 0, type: null};
    }

    // Entferne gemeinsame Präfixe
    const cleanName1 = name1.replace(/^(id_)?/, '');
    const cleanName2 = name2.replace(/^(id_)?/, '');

    // Pattern 1: Numerische Suffixe (field1, field2, field3)
    const numPattern1 = cleanName1.match(/^(.+?)(\d+)$/);
    const numPattern2 = cleanName2.match(/^(.+?)(\d+)$/);

    if (numPattern1 && numPattern2 && numPattern1[1] === numPattern2[1]) {
        return {
            score: 0.9,
            type: 'numeric_series',
            basePattern: numPattern1[1],
            numbers: [numPattern1[2], numPattern2[2]]
        };
    }

    // Pattern 2: Ähnliche Basis mit verschiedenen Suffixen
    let commonPrefixLength = 0;
    for (let i = 0; i < Math.min(cleanName1.length, cleanName2.length); i++) {
        if (cleanName1[i] === cleanName2[i]) {
            commonPrefixLength++;
        } else {
            break;
        }
    }

    if (commonPrefixLength >= 3) {
        const similarity = commonPrefixLength / Math.max(cleanName1.length, cleanName2.length);
        return {
            score: similarity,
            type: 'similar_prefix',
            commonPrefix: cleanName1.substring(0, commonPrefixLength)
        };
    }

    return {score: 0, type: null};
};

/**
 * Determine visual visibility of an element based on computed styles.
 *
 * This function analyzes the computed CSS styles to determine if an element
 * is visually visible to the user. It checks multiple CSS properties that
 * can affect visibility including display, visibility, opacity, and dimensions.
 * It also traverses up the DOM tree to check if any parent elements are hidden.
 *
 * @param {HTMLElement} element - The element to check for visual visibility
 * @param {CSSStyleDeclaration} computedStyle - Optional pre-computed style object
 * @returns {boolean} True if the element is visually visible, false otherwise
 */
const getElementVisualVisibility = (element, computedStyle) => {
    // First check if the element itself is hidden by any method.
    if (!isElementDirectlyVisible(element, computedStyle)) {
        return false;
    }

    // Then check if any parent element is hidden.
    return !hasHiddenParent(element);
};

/**
 * Check if an element is directly visible (ignoring parent visibility).
 *
 * @param {HTMLElement} element - The element to check
 * @param {CSSStyleDeclaration} computedStyle - Optional pre-computed style object
 * @returns {boolean} True if the element itself is visible
 */
const isElementDirectlyVisible = (element, computedStyle) => {
    // Check for hidden attribute.
    if (element.hasAttribute('hidden')) {
        return false;
    }

    // Use provided computed style or calculate it.
    const styles = computedStyle || window.getComputedStyle(element);

    // Check if element is hidden via display property.
    if (styles.display === 'none') {
        return false;
    }

    // Check if element is hidden via visibility property.
    if (styles.visibility === 'hidden') {
        return false;
    }

    // Check if element has zero dimensions (width or height).
    const width = parseFloat(styles.width);
    const height = parseFloat(styles.height);
    if (width === 0 && height === 0) {
        return false;
    }

    return true;
};

/**
 * Check if any parent element is hidden.
 *
 * Since we now wait for all JavaScript to execute before analyzing the DOM,
 * we can rely on the current computed styles to accurately reflect visibility.
 *
 * @param {HTMLElement} element - The element whose parents to check
 * @returns {boolean} True if any parent is hidden, false otherwise
 */
const hasHiddenParent = (element) => {
    let parent = element.parentElement;

    while (parent && parent !== document.body && parent !== document.documentElement) {
        // Check for hidden attribute on parent elements.
        if (parent.hasAttribute('hidden') || parent.hidden === true) {
            return true;
        }

        // Check computed styles of parent elements.
        const parentStyles = window.getComputedStyle(parent);
        if (parentStyles.display === 'none' || parentStyles.visibility === 'hidden') {
            return true;
        }

        parent = parent.parentElement;
    }

    return false;
};
