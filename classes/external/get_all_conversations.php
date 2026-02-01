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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * External function for retrieving a list with all conversations.
 *
 * @package    block_ai_chat
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_all_conversations extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Block contextid.', VALUE_REQUIRED),
            'component' => new external_value(PARAM_COMPONENT, 'The component name calling the AI', VALUE_REQUIRED),
        ]);
    }

    /**
     * Retrieve all conversations for the current user in the given context.
     *
     * @param int $contextid the context id
     * @param string $component the component name of the plugin using block_ai_chat
     * @return array response array including status code and content array containing the conversation list
     */
    public static function execute(int $contextid, string $component): array {
        global $USER;
        [
            'contextid' => $contextid,
            'component' => $component,
        ] = self::validate_parameters(
            self::execute_parameters(),
            [
                'contextid' => $contextid,
                'component' => $component,
            ]
        );
        self::validate_context(\core\context_helper::instance_by_id($contextid));
        // We are making sure that only the owner of the conversations can retrieve them, so
        // we do not need further capabilities.
        require_capability('block/ai_chat:view', \context::instance_by_id($contextid));
        require_capability('local/ai_manager:use', \context::instance_by_id($contextid));

        // Read from local_ai_manager and get all own conversations.
        $result = [];
        // We limit to purpose 'chat' here because we do not want the requests from the integrated tiny_ai tools to be loaded
        // for displaying our conversations. This especially is a performance issue, because the field 'requestoptions' contains
        // base64 decoded images for purpose 'itt', for example, which slows down the database query extremely.
        $records = \local_ai_manager\ai_manager_utils::get_log_entries(
            $component,
            $contextid,
            $USER->id,
            0,
            false,
            '*',
            ['chat', 'agent'],
        );
        // Go over all log entries and create conversation items.
        foreach ($records as $record) {
            // Ignore values without itemid.
            if (empty($record->itemid)) {
                continue;
            }
            if (array_key_exists($record->itemid, $result)) {
                $currentconversationentry = $result[$record->itemid];
                $currentconversationentry['timecreated'] = max($currentconversationentry['timecreated'], $record->timecreated);
                $result[$record->itemid] = $currentconversationentry;
            } else {
                $result[$record->itemid] = [
                    'conversationid' => $record->itemid,
                    'timecreated' => $record->timecreated,
                    'title' => format_string($record->prompttext),
                ];
            }
        }

        if (!empty($result)) {
            uasort($result, function ($a, $b) {
                return $b['timecreated'] <=> $a['timecreated'];
            });
        }

        return ['code' => 200, 'content' => $result];
    }

    /**
     * Returns the structure for retrieving the conversations.
     *
     * @return external_single_structure external function response structure containing the list of conversations
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure(
            [
                'code' => new external_value(PARAM_INT, 'The response code'),
                'message' => new external_value(PARAM_TEXT, 'The response message', VALUE_DEFAULT, ''),
                'debuginfo' => new external_value(PARAM_TEXT, 'Debug information', VALUE_DEFAULT, ''),
                'content' =>
                    new external_multiple_structure(
                        new external_single_structure([
                            'conversationid' => new external_value(PARAM_INT, 'ID of conversation'),
                            'title' => new external_value(PARAM_RAW, 'Title of conversation'),
                            'timecreated' => new external_value(PARAM_TEXT, 'Creationtimestamp'),
                        ]),
                        'List of conversations'
                    ),
            ],
        );
    }
}
