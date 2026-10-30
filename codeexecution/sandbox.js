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
 * Bridge between the chat window and the Python worker inside the sandboxed iframe (no AMD available here).
 *
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
(() => {
    'use strict';

    const config = document.currentScript.dataset;
    let worker = null;

    const postToParent = (message) => window.parent.postMessage(message, config.parentOrigin);

    const getWorker = () => {
        if (worker) {
            return worker;
        }
        // A worker from a URL would be cross-origin to this opaque origin, a blob worker is allowed.
        const source = 'self.aichatConfig = ' + JSON.stringify({
            pyodideUrl: config.pyodideUrl,
            rdkitUrl: config.rdkitUrl,
            schoolAxes: config.schoolAxes === '1',
        }) + ';\nimportScripts(' + JSON.stringify(config.workerUrl) + ');\n';
        worker = new Worker(URL.createObjectURL(new Blob([source], {type: 'text/javascript'})));
        worker.addEventListener('message', (event) => postToParent(event.data));
        worker.addEventListener('error', (event) => {
            postToParent({type: 'fatal', message: event.message || 'Worker error'});
        });
        return worker;
    };

    window.addEventListener('message', (event) => {
        if (event.source !== window.parent || event.origin !== config.parentOrigin) {
            return;
        }
        const data = event.data;
        if (!data || !Number.isInteger(data.id)) {
            return;
        }
        if (data.type === 'rotate' && Number.isInteger(data.handle) && Number.isFinite(data.elev)
                && Number.isFinite(data.azim)) {
            getWorker().postMessage({
                type: 'rotate', id: data.id, handle: data.handle, elev: data.elev, azim: data.azim, draft: data.draft === true,
            });
            return;
        }
        if (data.type !== 'run' || typeof data.code !== 'string') {
            return;
        }
        const sliderValues = {};
        Object.entries(data.sliderValues ?? {}).forEach(([key, value]) => {
            if (/^s\d+$/.test(key) && Number.isFinite(value)) {
                sliderValues[key] = value;
            }
        });
        getWorker().postMessage({type: 'run', id: data.id, code: data.code, sliderValues});
    });

    postToParent({type: 'ready'});
})();
