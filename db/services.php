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
 * External functions and service declaration for ai_chat
 *
 * Documentation: {@link https://moodledev.io/docs/apis/subsystems/external/description}
 *
 * @package    block_ai_chat
 * @category   webservice
 * @copyright  2024 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_ai_chat_get_all_conversations' => [
        'classname' => 'block_ai_chat\external\get_all_conversations',
        'methodname' => 'execute',
        'description' => 'Get all conversations.',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view', 'local/ai_manager:use',
    ],
    'block_ai_chat_delete_conversation' => [
        'classname' => 'block_ai_chat\external\delete_conversation',
        'methodname' => 'execute',
        'description' => 'Delete/Hide conversation from history.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view', 'local/ai_manager:use',
    ],
    'block_ai_chat_select_persona' => [
        'classname' => 'block_ai_chat\external\select_persona',
        'methodname' => 'execute',
        'description' => 'Select persona.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:edit',
    ],
    'block_ai_chat_get_personas' => [
        'classname' => 'block_ai_chat\external\get_personas',
        'methodname' => 'execute',
        'description' => 'Get all personas',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view',
    ],
    'block_ai_chat_get_messages' => [
        'classname' => 'block_ai_chat\external\get_messages',
        'methodname' => 'execute',
        'description' => 'Get all messages',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view', 'local/ai_manager:use',
    ],
    'block_ai_chat_request_ai' => [
        'classname' => 'block_ai_chat\external\request_ai',
        'methodname' => 'execute',
        'description' => 'Interact with the AI',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view', 'local/ai_manager:use',
    ],
    'block_ai_chat_create_dummy_persona' => [
        'classname' => 'block_ai_chat\external\create_dummy_persona',
        'methodname' => 'execute',
        'description' => 'Create dummy persona',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view',
    ],
    'block_ai_chat_delete_persona' => [
        'classname' => 'block_ai_chat\external\delete_persona',
        'methodname' => 'execute',
        'description' => 'Delete persona',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view', 'block/ai_chat:managepersonatemplates',
    ],
    'block_ai_chat_duplicate_persona' => [
        'classname' => 'block_ai_chat\external\duplicate_persona',
        'methodname' => 'execute',
        'description' => 'Duplicate a persona',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view',
    ],
    'block_ai_chat_get_initial_state' => [
        'classname' => 'block_ai_chat\external\get_initial_state',
        'methodname' => 'execute',
        'description' => 'Get the initial state',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/ai_chat:view',
    ],
];
