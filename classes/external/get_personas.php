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
 * Class reload_persona.
 *
 * @package    block_ai_chat
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_personas extends external_api {
    /**
     * Describes the input parameters.
     *
     * @return external_function_parameters the parameters that the function accepts
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contextid' => new external_value(PARAM_INT, 'Block contextid.', VALUE_REQUIRED),
        ]);
    }

    /**
     * Get all personas for the given context.
     *
     * @param int $contextid the context id
     * @param int $userid the user id
     * @return array response array including status code and content array containing reactive state updates
     */
    public static function execute(int $contextid, int $userid): array {
        [
            'contextid' => $contextid,
        ] =
            self::validate_parameters(self::execute_parameters(), [
                'contextid' => $contextid,
            ]);
        self::validate_context(\core\context_helper::instance_by_id($contextid));
        require_capability('block/ai_chat:view', \context::instance_by_id($contextid));

        $manager = new manager($contextid);
        $returnarray = [];
        foreach ($manager->get_personas($userid) as $persona) {
            $returnarray[] = [
                'name' => 'personas',
                'action' => 'put',
                'fields' => json_encode($persona),
            ];
        }
        return $returnarray;
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
