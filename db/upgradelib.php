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
 * Upgrade functions for AI Chat.
 *
 * @package   block_ai_chat
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * It has been overseen that some data is not being removed when deleting block instances.
 */
function block_ai_chat_cleanup_left_over_data() {
    global $DB;
    $tables = ['block_ai_chat_personas_selected', 'block_ai_chat_options'];
    foreach ($tables as $table) {
        $sql = "SELECT t.id FROM {{$table}} t
            LEFT JOIN {context} c ON t.contextid = c.id
            WHERE c.id IS NULL";
        $orphanedids = $DB->get_fieldset_sql($sql);
        if (!empty($orphanedids)) {
            $DB->delete_records_list($table, 'id', $orphanedids);
        }
    }
}
