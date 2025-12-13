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
 * Plugin generator class.
 *
 * @package   block_ai_chat
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_ai_chat_generator extends testing_block_generator {
    /**
     * Create a test persona.
     *
     * @param array|null $record Optional persona data to override defaults
     * @return stdClass The created persona record
     */
    public function create_persona(?array $record = null): stdClass {
        global $DB;

        $clock = \core\di::get(\core\clock::class);
        $time = $clock->time();

        // Default values for persona.
        $defaults = [
            'userid' => get_admin()->id,
            'name' => 'Test Persona ' . uniqid(),
            'prompt' => 'This is a test prompt for the persona.',
            'userinfo' => 'This is test user information.',
            'type' => \block_ai_chat\local\persona::TYPE_USER,
            'timecreated' => $time,
            'timemodified' => $time,
        ];

        // Merge provided record with defaults.
        $personadata = (object) array_merge($defaults, (array) $record);

        // Insert persona into database.
        $personadata->id = $DB->insert_record('block_ai_chat_personas', $personadata);

        return $personadata;
    }
}
