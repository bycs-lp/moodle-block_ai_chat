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
 * Management site: Manage AI context that should be sent along with requests.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

require_login();

global $DB, $OUTPUT, $PAGE;

$url = new moodle_url('/blocks/ai_chat/manage_aicontext.php', []);
$PAGE->set_url($url);
$PAGE->set_context(context_system::instance());
$PAGE->set_heading(get_string('manageaicontext', 'block_ai_chat'));

require_capability('block/ai_chat:manageaicontext', context_system::instance());

echo $OUTPUT->header();

// TODO Make a table_sql out of this table.
$aicontextrecords = $DB->get_records('block_ai_chat_aicontext');
$aicontexts = [];
foreach ($aicontextrecords as $aicontext) {
    $pagetypes = $DB->get_fieldset('block_ai_chat_aicontext_usage', 'pagetype', ['aicontextid' => $aicontext->id]);
    $aicontexts[] = [
        'id' => $aicontext->id,
        'name' => $aicontext->name,
        'description' => format_text($aicontext->description ?? '', FORMAT_PLAIN, ['filter' => false]),
        'content' => format_text($aicontext->content ?? '', FORMAT_PLAIN, ['filter' => false]),
        'pagetypes' => implode(', ', $pagetypes),
        'enabled' => $aicontext->enabled,
    ];
}

echo $OUTPUT->render_from_template('block_ai_chat/aicontexts', ['aicontexts' => array_values($aicontexts)]);

echo $OUTPUT->footer();
