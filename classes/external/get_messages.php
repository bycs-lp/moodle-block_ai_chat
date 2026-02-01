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

namespace block_ai_chat\external;

use block_ai_chat\manager;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * External function for retrieving the messages of a conversation.
 *
 * @package    block_ai_chat
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_messages extends external_api {
    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters the parameters that the function accepts
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Contextid', VALUE_REQUIRED),
            'component' => new external_value(PARAM_COMPONENT, 'The component name calling the AI', VALUE_REQUIRED),
            'conversationid' => new external_value(PARAM_INT, 'Conversation id', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Retrieve the messages of a conversation.
     *
     * @param int $contextid the context id
     * @param string $component the component name of the plugin using block_ai_chat
     * @param int $conversationid or 0, if all messages should be retrieved
     * @return array response array including status code and content array containing reactive state updates
     */
    public static function execute(int $contextid, string $component, int $conversationid): array {
        global $USER;
        [
            'contextid' => $contextid,
            'component' => $component,
            'conversationid' => $conversationid,
        ] =
            self::validate_parameters(self::execute_parameters(), [
                'contextid' => $contextid,
                'component' => $component,
                'conversationid' => $conversationid,
            ]);
        self::validate_context(\core\context_helper::instance_by_id($contextid));
        // Make sure the user has the proper capability.
        require_capability('block/ai_chat:view', \context::instance_by_id($contextid));
        require_capability('local/ai_manager:use', \context::instance_by_id($contextid));

        $manager = new manager($contextid, $component);
        return $manager->get_messages($USER->id, $conversationid);
    }

    /**
     * Returns the default update structure for the Reactive UI frontend.
     *
     * @return external_single_structure the update structure containing state updates
     */
    public static function execute_returns(): external_single_structure {
        return manager::get_update_structure();
    }
}
