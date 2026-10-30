<?php
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
 * Serves the bundled Pyodide and RDKit.js files to the code execution sandbox.
 *
 * The sandbox runs in an opaque origin, so every response needs CORS headers and no session is available. The
 * files are public static library files, they are only delivered while code execution is enabled site-wide.
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_DEBUG_DISPLAY', true);
define('NO_MOODLE_COOKIES', true);
require('../../../config.php');

use block_ai_chat\local\code_execution\asset_resolver;

$slashargument = ltrim((string) get_file_argument(), '/');
[$revision, $relativepath] = array_pad(explode('/', $slashargument, 2), 2, '');

$path = empty(get_config('block_ai_chat', 'enablecodeexecution')) ? null : (new asset_resolver())->resolve($relativepath);
if ($path === null) {
    header('HTTP/1.1 404 Not Found');
    die();
}

header('Content-Type: ' . asset_resolver::get_mimetype($path));
header('Content-Length: ' . filesize($path));
header('Access-Control-Allow-Origin: *');
header('Cross-Origin-Resource-Policy: cross-origin');
header('X-Content-Type-Options: nosniff');
if ($revision === asset_resolver::REVISION) {
    header('Cache-Control: public, max-age=31536000, immutable');
} else {
    header('Cache-Control: no-cache');
}
readfile($path);
