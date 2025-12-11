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

import {makeRequest} from 'local_ai_manager/make_request';

/**
 * Get the async answer from the local_ai_manager.
 *
 * @param {string} purpose
 * @param {string} prompt
 * @param {number} contextid
 * @param {array} options
 * @returns {string}
 */
export const askLocalAiManager = async (purpose, prompt, contextid, options = []) => {
    let result = {};
    try {
        result = await makeRequest(purpose, prompt, 'block_ai_chat', contextid, options);
    } catch (error) {
        result.code = 'aiconnector';
        result.result = error.error + " " + error.message;
        // For devs.
        result.result += error.backtrace;
    }
    return result;
};
