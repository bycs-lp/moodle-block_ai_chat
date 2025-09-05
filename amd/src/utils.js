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

import {call as fetchMany} from 'core/ajax';
import Log from 'core/log';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';
import {exception as displayException} from 'core/notification';

/**
 * Call external function and do error handling.
 *
 * @param {string} methodname the name of the external function to call
 * @param {string} args arguments for the external function
 * @returns {object|null} the content object or null in case of error
 */
export const callExternalFunction = async(methodname, args) => {
    let result = null;
    let error = null;
    try {
        result = await fetchMany([{
            methodname: methodname,
            args: args,
        }])[0];
    } catch (ex) {
        error = ex;
    }
    if (error !== null) {
        await displayException(error);
        return null;
    } else if (result && result.code !== 200) {
        let errorMessage = await getString('errorwithcode', 'block_ai_chat', result.code);
        if (result.message && result.message.length > 0) {
            errorMessage += '<br/><br/>' + result.message;
        }
        await showErrorToast(errorMessage);
        if (result.message && result.message.length > 0) {
            Log.error('Error calling external function ' + methodname);
            Log.error('Error message: ' + result.message);
        }
        if (result.debuginfo && result.debuginfo.length > 0) {
            Log.error('Debuginfo: ' + result.debuginfo);
        }
        return null;
    }
    return result.content;
};

export const callExternalFunctionReactiveUpdate = async(methodname, args) => {
    let result = await callExternalFunction(methodname, args);
    if (result !== null) {
        result.map((updateMessage) => {
            updateMessage.fields = JSON.parse(updateMessage.fields);
            return updateMessage;
        });
    }
    return result;
};

const showErrorToast = async(message) => {
    await addToast(message, {type: 'danger', autohide: false, closeButton: true});
};

/**
 * Hash function to get a hash of a string.
 *
 * @param {string} stringToHash the string to hash
 * @returns {Promise<string>} the promise containing a hex representation of the string encoded by SHA-256
 */
export const hash = async(stringToHash) => {
    const encoder = new TextEncoder();
    const data = encoder.encode(stringToHash);
    const hashAsArrayBuffer = await window.crypto.subtle.digest("SHA-256", data);
    const uint8ViewOfHash = new Uint8Array(hashAsArrayBuffer);
    return Array.from(uint8ViewOfHash)
        .map((b) => b.toString(16).padStart(2, "0"))
        .join("");
};

/**
 * Makes a straight text string without any HTML tags from a string that may contain HTML tags.
 * @param {string} textWithTags some HTML text
 * @returns {string} text without HTML tags
 */
export const stripHtmlTags = (textWithTags) => {
    // Replace <br> and variants with space
    let text = textWithTags.replace(/<br\s*\/?>/gi, ' ');
    // Remove all other HTML tags
    text = text.replace(/<[^>]*>/g, '');
    // Remove line breaks
    text = text.replace(/[\r\n]+/g, ' ');
    // Normalize multiple spaces to single space
    text = text.replace(/\s+/g, ' ');
    // Trim leading/trailing whitespace
    return text.trim();
};
