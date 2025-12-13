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
 * Upgrade code for block_ai_chat.
 *
 * @package   block_ai_chat
 * @copyright 2025 Tobias Garske, ISB Bayern
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * upgrade code
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_ai_chat_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2025021000) {
        // Install persona DB tables.
        $installxmlfile = $CFG->dirroot . '/blocks/ai_chat/db/install.xml';
        if (file_exists($installxmlfile)) {
            $dbman->install_from_xmldb_file($installxmlfile);
        } else {
            throw new moodle_exception('installxmlmissing', 'block_ai_chat');
        }
        // Fill with default personas.
        \block_ai_chat\local\persona::install_default_personas();

        upgrade_plugin_savepoint(true, 2025021000, 'block', 'ai_chat');
    }

    if ($oldversion < 2025121300) {
        $table = new xmldb_table('block_ai_chat_personas');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'userinfo');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        foreach ($DB->get_records('block_ai_chat_personas') as $persona) {
            $persona->type = intval($persona->userid) === 0
                ? \block_ai_chat\local\persona::TYPE_TEMPLATE
                : \block_ai_chat\local\persona::TYPE_USER;
            if (intval($persona->userid) === 0) {
                $persona->userid = get_admin()->id;
            }
            $DB->update_record('block_ai_chat_personas', $persona);
        }
        upgrade_block_savepoint(true, 2025121300, 'ai_chat');
    }

    return true;
}
