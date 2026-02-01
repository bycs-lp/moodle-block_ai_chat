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
use moodle_exception;

/**
 * Create a dummy persona which afterward can be edited.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class create_dummy_persona extends external_api {
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
            ]
        );
    }

    /**
     * Creates a dummy persona for the current user.
     *
     * @param int $contextid The context id of the AI chat
     * @param string $component the component name of the plugin using block_ai_chat
     * @return array response array including status code and content array containing reactive state updates
     */
    public static function execute(int $contextid, string $component): array {
        [
            'contextid' => $contextid,
            'component' => $component,
        ] = external_api::validate_parameters(
            self::execute_parameters(),
            [
                'contextid' => $contextid,
                'component' => $component,
            ]
        );

        $context = \context_helper::instance_by_id($contextid);
        self::validate_context($context);
        // View capability is sufficient as creating a dummy persona is bound to the own user
        // which should always be fine.
        // In theory even the view permission for the current context could be omitted, but
        // we keep it just in case.
        require_capability('block/ai_chat:view', $context);

        $manager = new manager($contextid, $component);
        return $manager->create_dummy_persona();
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
