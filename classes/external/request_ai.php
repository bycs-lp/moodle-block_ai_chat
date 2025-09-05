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
 * Send a message to the AI and return the answer in a reactive state update format.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_ai extends external_api {
    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters the parameters that the function accepts
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'contextid' => new external_value(PARAM_INT, 'The block_ai_chat context id'),
                'component' => new external_value(PARAM_COMPONENT, 'The component name calling the AI'),
                'mode' => new external_value(PARAM_ALPHA, 'The mode to be used ("chat" or "agent")'),
                'prompt' => new external_value(PARAM_RAW, 'The prompt to send to the AI'),
                'options' => new external_value(
                    PARAM_RAW,
                    'Additional options for the AI request as stringified JSON',
                    VALUE_DEFAULT,
                    '{}'
                ),
            ]
        );
    }

    /**
     * Sends a message to the AI and returns the answer in a reactive state update format.
     *
     * @param int $contextid The context id
     * @param string $component The component name of the plugin calling the AI
     * @param string $mode The mode to be used (can be "chat" or "agent")
     * @param string $prompt The prompt to send to the AI
     * @param string $options Additional options for the AI request as stringified JSON
     * @return array response array including status code and content array containing reactive state updates
     */
    public static function execute(int $contextid, string $component, string $mode, string $prompt, string $options): array {
        [
            'contextid' => $contextid,
            'component' => $component,
            'mode' => $mode,
            'prompt' => $prompt,
            'options' => $options,
        ] = external_api::validate_parameters(self::execute_parameters(), [
            'contextid' => $contextid,
            'component' => $component,
            'mode' => $mode,
            'prompt' => $prompt,
            'options' => $options,
        ]);

        $context = \context_helper::instance_by_id($contextid);
        self::validate_context($context);

        $options = json_decode($options, true);

        // We do not need capability for local_ai_manager, because it is checked in the ai_manager directly.
        require_capability('block/ai_chat:view', $context);
        if ($mode === 'agent') {
            require_capability('block/ai_chat:useagentmode', $context);
        }

        $manager = new manager($contextid, $component);
        return $manager->request_ai($prompt, $mode, $options);
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
