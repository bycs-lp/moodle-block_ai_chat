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
 * Executes Python code blocks of AI answers with Pyodide in a sandboxed iframe.
 *
 * @module     block_ai_chat/code_runner
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Config from 'core/config';
import Log from 'core/log';
import Templates from 'core/templates';
import {debounce} from 'core/utils';
import {getString, getStrings} from 'core/str';

const SANDBOX_PATH = '/blocks/ai_chat/codeexecution/sandbox.php';
const LOAD_TIMEOUT = 120000;
const RUN_TIMEOUT = 30000;
const MAX_TEXT_LENGTH = 20000;
const MAX_IMAGES = 10;
const MAX_IMAGE_BASE64_LENGTH = Math.ceil(5 * 1024 * 1024 / 3) * 4;
const IMAGE_MIMETYPES = ['image/png', 'image/svg+xml'];
const BASE64_PATTERN = /^[A-Za-z0-9+/]+={0,2}$/;
const CODE_BLOCK_SELECTOR = 'pre.language-python, pre.language-py';
const ENHANCED_ATTRIBUTE = 'data-block_ai_chat-coderunner';

const selectors = {
    RUN_BUTTON: '[data-block_ai_chat-action="runcode"]',
    STOP_BUTTON: '[data-block_ai_chat-action="stopcode"]',
    TOGGLE_BUTTON: '[data-block_ai_chat-action="togglecode"]',
    TOGGLE_LABEL: '[data-block_ai_chat-element="togglecodelabel"]',
    STATUS: '[data-block_ai_chat-element="coderunnerstatus"]',
    SLIDERS: '[data-block_ai_chat-element="coderunnersliders"]',
    OUTPUT: '[data-block_ai_chat-element="coderunneroutput"]',
};

const MAX_SLIDERS = 10;
const SLIDER_DEBOUNCE = 150;
const ROTATION_PER_PIXEL = 0.5;
const ROTATION_PER_KEY = 10;

/** @type {WeakMap<HTMLElement, {running: boolean, rerun: boolean}>} execution state per code runner */
const runnerStates = new WeakMap();

/**
 * Error for runs which have been aborted because the sandbox has been destroyed.
 */
class SandboxAbortedError extends Error {
    /**
     * Constructor.
     *
     * @param {string} reason one of 'timeout', 'stopped', 'error'
     */
    constructor(reason) {
        super(reason);
        this.reason = reason;
    }
}

/**
 * Hidden sandboxed iframe running the Python worker, shared by all code blocks of a chat instance.
 */
class Sandbox {
    /**
     * Constructor.
     *
     * @param {number} contextid the context id of the chat instance, the sandbox is only delivered if enabled there
     */
    constructor(contextid) {
        this.contextid = contextid;
        this.iframe = null;
        this.ready = null;
        this.resolveReady = null;
        this.runs = new Map();
        this.nextId = 1;
        this.messageHandler = this.handleMessage.bind(this);
    }

    /**
     * Creates the iframe if necessary.
     *
     * @returns {Promise} resolved as soon as the sandbox accepts code
     */
    load() {
        if (!this.ready) {
            this.ready = new Promise((resolve) => {
                this.resolveReady = resolve;
            });
            this.iframe = document.createElement('iframe');
            this.iframe.setAttribute('sandbox', 'allow-scripts');
            this.iframe.setAttribute('aria-hidden', 'true');
            this.iframe.setAttribute('tabindex', '-1');
            this.iframe.className = 'd-none';
            this.iframe.src = Config.wwwroot + SANDBOX_PATH + '?contextid=' + encodeURIComponent(this.contextid);
            window.addEventListener('message', this.messageHandler);
            document.body.appendChild(this.iframe);
        }
        return this.ready;
    }

    /**
     * Handles messages from the sandbox.
     *
     * @param {MessageEvent} event the message event
     */
    handleMessage(event) {
        if (!this.iframe || event.source !== this.iframe.contentWindow || !event.data || typeof event.data !== 'object') {
            return;
        }
        const data = event.data;
        if (data.type === 'ready') {
            this.resolveReady();
            return;
        }
        if (data.type === 'fatal') {
            Log.debug('block_ai_chat: code execution sandbox failed: ' + data.message);
            this.destroy('error');
            return;
        }
        const run = this.runs.get(data.id);
        if (!run) {
            return;
        }
        if (data.type === 'status') {
            run.onStatus(data.status);
        } else if (data.type === 'result') {
            this.runs.delete(data.id);
            run.resolve(data);
        }
    }

    /**
     * Executes Python code.
     *
     * @param {string} code the Python code
     * @param {Function} onStatus called with 'loading' or 'running'
     * @param {Object} sliderValues current slider values by slider key
     * @returns {Promise<Object>} the result of the worker
     */
    run(code, onStatus, sliderValues) {
        return this.send({type: 'run', code, sliderValues}, onStatus);
    }

    /**
     * Renders a kept 3D figure of a previous run from another viewing angle.
     *
     * @param {number} handle handle of the figure
     * @param {number} elev elevation angle in degrees
     * @param {number} azim azimuth angle in degrees
     * @param {boolean} draft whether to render with low resolution while dragging
     * @returns {Promise<Object>} the result of the worker
     */
    rotate(handle, elev, azim, draft) {
        return this.send({type: 'rotate', handle, elev, azim, draft}, () => undefined);
    }

    /**
     * Sends a request to the sandbox.
     *
     * @param {Object} message the request without id
     * @param {Function} onStatus called with status updates of the request
     * @returns {Promise<Object>} the result of the worker
     */
    send(message, onStatus) {
        const id = this.nextId++;
        const promise = new Promise((resolve, reject) => {
            this.runs.set(id, {resolve, reject, onStatus});
        });
        const ready = this.load();
        const iframe = this.iframe;
        ready.then(() => {
            if (this.iframe === iframe && this.runs.has(id)) {
                // The sandbox has an opaque origin, so there is no other target origin to address it.
                iframe.contentWindow.postMessage({...message, id}, '*');
            }
            return null;
        }).catch(Log.error);
        return promise;
    }

    /**
     * Removes the iframe and aborts all pending runs, the next run creates a fresh sandbox.
     *
     * @param {string} reason the reason passed to the aborted runs
     */
    destroy(reason) {
        window.removeEventListener('message', this.messageHandler);
        this.iframe?.remove();
        this.iframe = null;
        this.ready = null;
        const runs = [...this.runs.values()];
        this.runs.clear();
        runs.forEach((run) => run.reject(new SandboxAbortedError(reason)));
    }
}

const sandboxes = new Map();

/**
 * Returns the sandbox of a chat instance.
 *
 * @param {number} contextid the context id of the chat instance
 * @returns {Sandbox} the sandbox
 */
const getSandbox = (contextid) => {
    if (!sandboxes.has(contextid)) {
        sandboxes.set(contextid, new Sandbox(contextid));
    }
    return sandboxes.get(contextid);
};

/**
 * Checks that an image of the worker result can safely be used as data URL.
 *
 * @param {Object} image image object of the result
 * @returns {boolean} true if valid
 */
const isValidImage = (image) => image !== null && typeof image === 'object'
    && IMAGE_MIMETYPES.includes(image.mime)
    && typeof image.data === 'string'
    && image.data.length <= MAX_IMAGE_BASE64_LENGTH
    && BASE64_PATTERN.test(image.data);

/**
 * Creates a preformatted text block.
 *
 * @param {string} text the text, inserted as plain text
 * @param {string} classes additional CSS classes
 * @returns {HTMLElement} the element
 */
const createTextBlock = (text, classes) => {
    const pre = document.createElement('pre');
    pre.className = 'small border rounded p-2 mt-2 mb-0 ' + classes;
    pre.textContent = text.substring(0, MAX_TEXT_LENGTH);
    return pre;
};

/**
 * Makes the image of a 3D figure rotatable by dragging with the mouse or with the arrow keys.
 *
 * @param {HTMLImageElement} img the image
 * @param {Sandbox} sandbox the sandbox keeping the figure
 * @param {number} handle handle of the figure in the sandbox
 * @param {number[]} view initial elevation and azimuth in degrees
 */
const makeRotatable = async(img, sandbox, handle, view) => {
    let [elev, azim] = view;
    let busy = false;
    let pending = null;
    let start = null;
    const hint = await getString('executionrotatehint', 'block_ai_chat');
    img.classList.add('block_ai_chat-rotatable');
    img.tabIndex = 0;
    img.draggable = false;
    img.title = hint;
    img.alt += '. ' + hint;

    // Only one rendering at a time, intermediate angles are skipped while the sandbox is busy.
    const render = async(draft) => {
        if (busy) {
            pending = pending === false ? false : draft;
            return;
        }
        busy = true;
        try {
            const result = await sandbox.rotate(handle, elev, azim, draft);
            const image = Array.isArray(result.images) ? result.images[0] : null;
            if (image && isValidImage(image)) {
                img.src = 'data:' + image.mime + ';base64,' + image.data;
            }
        } catch (exception) {
            if (!(exception instanceof SandboxAbortedError)) {
                Log.error(exception);
            }
        }
        busy = false;
        if (pending !== null) {
            const draftpending = pending;
            pending = null;
            render(draftpending);
        }
    };
    const clampElevation = (value) => Math.max(-90, Math.min(90, value));

    img.addEventListener('pointerdown', (event) => {
        start = {x: event.clientX, y: event.clientY, elev, azim};
        img.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    img.addEventListener('pointermove', (event) => {
        if (!start) {
            return;
        }
        azim = start.azim - (event.clientX - start.x) * ROTATION_PER_PIXEL;
        elev = clampElevation(start.elev + (event.clientY - start.y) * ROTATION_PER_PIXEL);
        render(true);
    });
    const end = () => {
        if (start) {
            start = null;
            render(false);
        }
    };
    img.addEventListener('pointerup', end);
    img.addEventListener('pointercancel', end);
    img.addEventListener('keydown', (event) => {
        const changes = {
            ArrowLeft: [0, ROTATION_PER_KEY],
            ArrowRight: [0, -ROTATION_PER_KEY],
            ArrowUp: [-ROTATION_PER_KEY, 0],
            ArrowDown: [ROTATION_PER_KEY, 0],
        }[event.key];
        if (!changes) {
            return;
        }
        event.preventDefault();
        elev = clampElevation(elev + changes[0]);
        azim += changes[1];
        render(false);
    });
};

/**
 * Renders the result of the worker into the output area, replacing the previous output.
 *
 * @param {HTMLElement} output the output area
 * @param {Object} result the result of the worker
 * @param {Sandbox} sandbox the sandbox which created the result
 */
const renderResult = async(output, result, sandbox) => {
    const fragment = document.createDocumentFragment();
    if (typeof result.stdout === 'string' && result.stdout !== '') {
        fragment.appendChild(createTextBlock(result.stdout, 'bg-light'));
    }
    if (result.truncated === true) {
        const note = document.createElement('div');
        note.className = 'small text-muted';
        note.textContent = await getString('executionoutputtruncated', 'block_ai_chat');
        fragment.appendChild(note);
    }
    const images = Array.isArray(result.images) ? result.images.filter(isValidImage).slice(0, MAX_IMAGES) : [];
    for (const [index, image] of images.entries()) {
        const img = document.createElement('img');
        img.className = 'img-fluid d-block bg-white border rounded p-1 mt-2';
        img.alt = await getString('executionoutputimage', 'block_ai_chat', index + 1);
        const rotatable = Number.isInteger(image.handle) && Array.isArray(image.view)
            && image.view.length === 2 && image.view.every(Number.isFinite);
        if (rotatable) {
            // Keep the display size when low resolution drafts are shown while rotating.
            img.addEventListener('load', () => {
                img.width = img.naturalWidth;
            }, {once: true});
            await makeRotatable(img, sandbox, image.handle, image.view);
        }
        img.src = 'data:' + image.mime + ';base64,' + image.data;
        fragment.appendChild(img);
    }
    if (typeof result.error === 'string' && result.error !== '') {
        fragment.appendChild(createTextBlock(result.error, 'text-danger bg-light'));
    }
    output.replaceChildren(fragment);
};

/**
 * Checks a slider definition of the worker result.
 *
 * @param {Object} slider slider definition
 * @returns {boolean} true if valid
 */
const isValidSlider = (slider) => slider !== null && typeof slider === 'object'
    && typeof slider.key === 'string' && /^s\d+$/.test(slider.key)
    && typeof slider.label === 'string'
    && [slider.min, slider.max, slider.step, slider.value].every(Number.isFinite)
    && slider.min < slider.max && slider.step > 0;

/**
 * Formats a slider value with the precision of its step.
 *
 * @param {number} value the value
 * @param {number} step the step of the slider
 * @returns {string} the formatted value
 */
const formatSliderValue = (value, step) => {
    const decimals = Math.min(6, Math.max(0, -Math.floor(Math.log10(step))));
    return value.toLocaleString(undefined, {minimumFractionDigits: decimals, maximumFractionDigits: decimals});
};

/**
 * Returns the current values of the sliders of a code runner.
 *
 * @param {HTMLElement} runner the code runner element
 * @returns {Object} slider values by slider key
 */
const getSliderValues = (runner) => {
    const values = {};
    runner.querySelectorAll(selectors.SLIDERS + ' input[type="range"]').forEach((input) => {
        values[input.dataset.key] = parseFloat(input.value);
    });
    return values;
};

/**
 * Creates the HTML sliders for the matplotlib sliders of the code, unless they already exist.
 *
 * @param {HTMLElement} pre the code block
 * @param {HTMLElement} runner the code runner element
 * @param {Sandbox} sandbox the sandbox of the chat instance
 * @param {Array} sliders slider definitions of the worker result
 */
const renderSliders = (pre, runner, sandbox, sliders) => {
    const container = runner.querySelector(selectors.SLIDERS);
    const valid = Array.isArray(sliders) ? sliders.filter(isValidSlider).slice(0, MAX_SLIDERS) : [];
    const keys = valid.map((slider) => slider.key).join();
    if (container.dataset.keys === keys) {
        return;
    }
    container.dataset.keys = keys;
    container.replaceChildren(...valid.map((slider) => {
        const id = pre.id + '-' + slider.key;
        const row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 mt-2';
        const label = document.createElement('label');
        label.className = 'small mb-0 text-nowrap';
        label.htmlFor = id;
        label.textContent = slider.label.substring(0, 100);
        const input = document.createElement('input');
        input.type = 'range';
        input.className = 'form-range';
        input.id = id;
        input.dataset.key = slider.key;
        input.min = slider.min;
        input.max = slider.max;
        input.step = slider.step;
        input.value = slider.value;
        const value = document.createElement('output');
        value.className = 'small text-nowrap';
        value.htmlFor = id;
        value.textContent = formatSliderValue(slider.value, slider.step);
        const rerun = debounce(() => execute(pre, runner, sandbox).catch(Log.error), SLIDER_DEBOUNCE);
        input.addEventListener('input', () => {
            value.textContent = formatSliderValue(parseFloat(input.value), slider.step);
            rerun();
        });
        row.append(label, input, value);
        return row;
    }));
};

/**
 * Executes the code of a code block and displays the result.
 *
 * @param {HTMLElement} pre the code block
 * @param {HTMLElement} runner the code runner element belonging to the code block
 * @param {Sandbox} sandbox the sandbox of the chat instance
 */
const execute = async(pre, runner, sandbox) => {
    const state = runnerStates.get(runner) ?? {running: false, rerun: false};
    runnerStates.set(runner, state);
    if (state.running) {
        // A slider has been moved during a run, run again with the latest values afterwards.
        state.rerun = true;
        return;
    }
    state.running = true;
    const runButton = runner.querySelector(selectors.RUN_BUTTON);
    const stopButton = runner.querySelector(selectors.STOP_BUTTON);
    const status = runner.querySelector(selectors.STATUS);
    const output = runner.querySelector(selectors.OUTPUT);
    const strings = await getStrings([
        'executionloading', 'executionrunning', 'executiondone', 'executionerror', 'executiontimeout', 'executionstopped',
    ].map((key) => ({key, component: 'block_ai_chat'})));
    const [loading, running, done, failed, timedout, stopped] = strings;

    runButton.disabled = true;
    stopButton.classList.remove('d-none');
    status.textContent = loading;
    let timer = setTimeout(() => sandbox.destroy('timeout'), LOAD_TIMEOUT);
    const onStatus = (state) => {
        if (state === 'running') {
            clearTimeout(timer);
            timer = setTimeout(() => sandbox.destroy('timeout'), RUN_TIMEOUT);
            status.textContent = running;
        }
    };
    try {
        const result = await sandbox.run(pre.textContent, onStatus, getSliderValues(runner));
        renderSliders(pre, runner, sandbox, result.sliders);
        await renderResult(output, result, sandbox);
        status.textContent = result.error ? failed : done;
    } catch (exception) {
        if (!(exception instanceof SandboxAbortedError)) {
            throw exception;
        }
        state.rerun = false;
        status.textContent = {timeout: timedout, stopped: stopped}[exception.reason] ?? failed;
    } finally {
        clearTimeout(timer);
        stopButton.classList.add('d-none');
        runButton.disabled = false;
        state.running = false;
    }
    if (state.rerun) {
        state.rerun = false;
        await execute(pre, runner, sandbox);
    }
};

/**
 * Shows or hides the code block belonging to a code runner.
 *
 * @param {HTMLElement} pre the code block
 * @param {HTMLElement} runner the code runner element belonging to the code block
 */
const toggleCode = async(pre, runner) => {
    const button = runner.querySelector(selectors.TOGGLE_BUTTON);
    const show = button.getAttribute('aria-expanded') !== 'true';
    pre.classList.toggle('d-none', !show);
    button.setAttribute('aria-expanded', show ? 'true' : 'false');
    runner.querySelector(selectors.TOGGLE_LABEL).textContent =
        await getString(show ? 'hidecode' : 'showcode', 'block_ai_chat');
};

/**
 * Adds the code runner controls below a code block and hides the code.
 *
 * @param {HTMLElement} pre the code block
 * @param {Sandbox} sandbox the sandbox of the chat instance
 */
const enhanceCodeBlock = async(pre, sandbox) => {
    if (!pre.id) {
        pre.id = 'block_ai_chat-code-' + Math.random().toString(36).slice(2);
    }
    const {html, js} = await Templates.renderForPromise('block_ai_chat/code_runner', {codeid: pre.id});
    const container = document.createElement('div');
    pre.classList.add('d-none');
    pre.after(container);
    Templates.replaceNodeContents(container, html, js);
    const runner = container.querySelector('[data-block_ai_chat-element="coderunner"]');
    runner.querySelector(selectors.RUN_BUTTON).addEventListener('click', () => execute(pre, runner, sandbox).catch(Log.error));
    runner.querySelector(selectors.STOP_BUTTON).addEventListener('click', () => sandbox.destroy('stopped'));
    runner.querySelector(selectors.TOGGLE_BUTTON).addEventListener('click', () => toggleCode(pre, runner).catch(Log.error));
};

/**
 * Adds the code runner controls to all Python code blocks inside an element.
 *
 * @param {HTMLElement} element the element, usually a chat message
 * @param {number} contextid the context id of the chat instance
 */
export const enhance = (element, contextid) => {
    const sandbox = getSandbox(contextid);
    element.querySelectorAll(CODE_BLOCK_SELECTOR).forEach((pre) => {
        if (pre.hasAttribute(ENHANCED_ATTRIBUTE)) {
            return;
        }
        pre.setAttribute(ENHANCED_ATTRIBUTE, '1');
        enhanceCodeBlock(pre, sandbox).catch(Log.error);
    });
};

/**
 * Removes all code runner controls inside an element, shows the code again and stops the sandbox.
 *
 * @param {HTMLElement} element the element, usually the chat output
 * @param {number} contextid the context id of the chat instance
 */
export const reset = (element, contextid) => {
    getSandbox(contextid).destroy('stopped');
    element.querySelectorAll('[data-block_ai_chat-element="coderunner"]').forEach((runner) => runner.parentElement.remove());
    element.querySelectorAll('[' + ENHANCED_ATTRIBUTE + ']').forEach((pre) => {
        pre.removeAttribute(ENHANCED_ATTRIBUTE);
        pre.classList.remove('d-none');
    });
};
