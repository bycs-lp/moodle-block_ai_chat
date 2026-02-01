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

use block_ai_chat\local\persona;

/**
 * Restore steps for block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_ai_chat_block_structure_step extends restore_structure_step {
    /**
     * Define structure.
     */
    protected function define_structure(): array {
        $paths = [];
        $paths[] = new restore_path_element('aichat', '/block/aichat');
        $paths[] = new restore_path_element('persona', '/block/aichat/personas/persona');
        $paths[] = new restore_path_element('persona_selected', '/block/aichat/personas_selected/persona_selected');
        $paths[] = new restore_path_element('chat_option', '/block/aichat/chat_options/chat_option');

        return $paths;
    }

    /**
     * Process an aichat element. Nothing to do here yet as there is no data to restore.
     *
     * @param array $data
     */
    protected function process_aichat(array $data): void {
    }

    /**
     * Process a persona element.
     *
     * @param array $data
     */
    protected function process_persona(array $data): void {
        global $DB;

        $userinfo = $this->get_setting_value('users');

        // If no userinfo, map to current user.
        if (!$userinfo) {
            $data['userid'] = $this->task->get_userid();
        } else {
            $data['userid'] = $this->get_mappingid('user', $data['userid']);
        }

        $search = [
            'name' => $data['name'],
            'prompt' => $data['prompt'],
            'type' => $data['type'],
            'userinfo' => $data['userinfo'],
        ];

        $where = 'name = :name AND type = :type';

        if ($data['type'] != persona::TYPE_TEMPLATE) {
            // For user-specific personas, add userid to search.
            $search['userid'] = $data['userid'];
            $where .= ' AND userid = :userid';
        }

        $where .= ' AND ' . $DB->sql_compare_text('prompt') . ' = ' . $DB->sql_compare_text(':prompt');
        $where .= ' AND ' . $DB->sql_compare_text('userinfo') . ' = ' . $DB->sql_compare_text(':userinfo');

        $persona = $DB->get_record_select('block_ai_chat_personas', $where, $search, 'id', IGNORE_MULTIPLE | IGNORE_MISSING);
        if ($persona) {
            // Persona already exists, map to existing record.
            $this->set_mapping('block_ai_chat_personas', $data['id'], $persona->id);
            return;
        }
        $data = (object)$data;
        // Restored personas are always of type user.
        $data->type = persona::TYPE_USER;
        $oldid = $data->id;
        $data->userid = $this->task->get_userid();

        $newid = $DB->insert_record('block_ai_chat_personas', $data);
        $this->set_mapping('block_ai_chat_personas', $oldid, $newid);
    }

    /**
     * Process a persona_selected element.
     *
     * @param array $data
     */
    protected function process_persona_selected(array $data): void {
        global $DB;
        $data = (object) $data;
        $data->contextid = $this->get_mappingid('context', $data->contextid);
        $data->personasid = $this->get_mappingid('block_ai_chat_personas', $data->personasid);
        $DB->insert_record('block_ai_chat_personas_selected', $data);
    }

    /**
     * Process a chat_option element.
     *
     * @param array $data
     */
    protected function process_chat_option(array $data): void {
        global $DB;
        $data = (object) $data;
        $data->contextid = $this->get_mappingid('context', $data->contextid);
        $DB->insert_record('block_ai_chat_options', $data);
    }
}
