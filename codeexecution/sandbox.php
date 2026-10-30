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
 * Sandbox document for executing AI generated Python code with Pyodide.
 *
 * The CSP sandbox directive gives the document an opaque origin, so the executed code has no access to the
 * Moodle DOM, cookies or session, and network access is limited to the bundled library files. The sandbox is only
 * delivered for chat instances in which code execution is available.
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
use block_ai_chat\manager;

$contextid = optional_param('contextid', 0, PARAM_INT);
if ($contextid <= 0 || !context::instance_by_id($contextid, IGNORE_MISSING) || !manager::is_code_execution_available($contextid)) {
    header('HTTP/1.1 404 Not Found');
    die();
}

$wwwrootparts = parse_url($CFG->wwwroot);
$parentorigin = $wwwrootparts['scheme'] . '://' . $wwwrootparts['host']
    . (isset($wwwrootparts['port']) ? ':' . $wwwrootparts['port'] : '');
$scriptbaseurl = $CFG->wwwroot . '/blocks/ai_chat/codeexecution/';
$assetbaseurl = $scriptbaseurl . 'asset.php/';

// RDKit.js (Emscripten embind) needs 'unsafe-eval'; it grants nothing the executed Python cannot do via its js module.
$csp = [
    'sandbox allow-scripts',
    "default-src 'none'",
    "script-src {$scriptbaseurl} 'wasm-unsafe-eval' 'unsafe-eval'",
    'worker-src blob:',
    "connect-src {$assetbaseurl}",
    "frame-ancestors {$parentorigin}",
    "base-uri 'none'",
    "form-action 'none'",
];
header('Content-Security-Policy: ' . implode('; ', $csp));
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-cache');

$attributes = [
    'src' => $scriptbaseurl . 'sandbox.js?rev=' . rawurlencode(asset_resolver::REVISION),
    'data-parent-origin' => $parentorigin,
    'data-worker-url' => $scriptbaseurl . 'worker.js?rev=' . rawurlencode(asset_resolver::REVISION),
    'data-pyodide-url' => asset_resolver::get_library_url($CFG->wwwroot, 'pyodide'),
    'data-rdkit-url' => asset_resolver::get_library_url($CFG->wwwroot, 'rdkit'),
    'data-school-axes' => empty(get_config('block_ai_chat', 'schoolcoordinatesystem')) ? '0' : '1',
];
$scripttag = '<script';
foreach ($attributes as $name => $value) {
    $scripttag .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
}
$scripttag .= '></script>';

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>block_ai_chat sandbox</title>' . $scripttag
    . '</head><body></body></html>';
