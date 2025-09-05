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

namespace block_ai_chat\local;

use stdClass;

/**
 * Utility functions for handling of personas.
 *
 * @package    block_ai_chat
 * @copyright  2025 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persona {
    /**
     * @var int TYPE_TEMPLATE Declares a persona as system-wide template.
     *
     * Value has to be in sync with definition in JS module block_ai_chat/personalistitem.
     */
    public const TYPE_TEMPLATE = 0;

    /**
     * @var int TYPE_USER Declares a persona as user template.
     *
     * Value has to be in sync with definition in JS module block_ai_chat/personalistitem.
     */
    public const TYPE_USER = 1;

    /**
     * Fills personas table with default personas.
     *
     * @return void
     */
    public static function install_default_personas(): void {
        global $DB;
        $records = [];
        $records[] = (object) [
            'userid' => 0,
            'name' => 'Persona 1',
            'prompt' => 'You are a helpful assistant.',
            'userinfo' => 'You are speaking to a helpful assistant. You can ask questions about anything.',
            'type' => self::TYPE_TEMPLATE,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $records[] = (object) [
            'userid' => 0,
            'name' => 'Persona 2',
            'prompt' => 'You are a unhelpful assistant.',
            'userinfo' => 'You are speaking to a unhelpful assistant. You can ask questions about anything.',
            'type' => self::TYPE_TEMPLATE,
            'timecreated' => time(),
            'timemodified' => time(),
        ];

        $DB->insert_records('block_ai_chat_personas', $records);
    }

    /**
     * Get all relevant personas for this instance.
     *
     * @param int $userid The user id
     * @return array of persona objects or empty, if none found
     */
    public static function get_all_personas(int $userid): array {
        global $DB;

        $sql = "SELECT id, userid, name, prompt, userinfo, type FROM {block_ai_chat_personas}
                WHERE (userid = :userid AND type = :typeuser) OR
                (type = :typetemplate)";
        $params = [
            'userid' => $userid,
            'typeuser' => self::TYPE_USER,
            'typetemplate' => self::TYPE_TEMPLATE,
        ];
        $personas = $DB->get_records_sql($sql, $params);
        return empty($personas) ? [] : $personas;
    }

    /**
     * Get the currently selected persona for a given context.
     *
     * @param int $contextid The context id
     * @return int The persona id, or 0 if none is selected
     */
    public static function get_current_persona_id(int $contextid): int {
        global $DB;

        $record = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $contextid]);
        if (!$record) {
            return 0;
        }
        return $record->personasid;
    }

    /**
     * Retrieves the currently selected persona for a given context.
     *
     * @param int $contextid the context id
     * @return stdClass|null the persona object or null if none is selected
     */
    public static function get_current_persona(int $contextid): ?stdClass {
        global $DB;

        $personaid = self::get_current_persona_id($contextid);
        if ($personaid === 0) {
            return null;
        }
        return $DB->get_record('block_ai_chat_personas', ['id' => $personaid]);
    }
}
