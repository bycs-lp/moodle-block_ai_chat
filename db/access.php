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
 * Capabilities for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$capabilities = [

    'block/ai_chat:myaddinstance' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'user' => CAP_PREVENT,
        ],

        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],

    'block/ai_chat:addinstance' => [
        'riskbitmask' => RISK_SPAM | RISK_XSS,

        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_PREVENT,
            'manager' => CAP_PREVENT,
        ],

        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
    'block/ai_chat:view' => [
        'captype' => 'read',
        // We intentionally choose CONTEXT_MODULE, because this capability will also be used by
        // mod_aichat in the module context.
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_PREVENT,
        ],

        'clonepermissionsfrom' => 'moodle/my:manageblocks',
    ],
    'block/ai_chat:edit' => [
        'captype' => 'write',
        // We intentionally choose CONTEXT_MODULE, because this capability will also be used by
        // mod_aichat in the module context.
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],
    'block/ai_chat:managepersonatemplates' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:config',
    ],
    'block/ai_chat:manageaicontext' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:config',
    ],
    'block/ai_chat:useagentmode' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/ai_chat:edit',
    ],

    // MBS-10761: Form-assist capability (dedicated to in-form suggestions, mode `formassist`).
    // Kept separate from `useagent` so form-assist can be granted without giving the full tool-agent.
    'block/ai_chat:useformassist' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/ai_chat:useagentmode',
    ],

    // MBS-10761: Tool-agent capability (mode `toolagent`, server-side tool execution).
    'block/ai_chat:useagent' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/ai_chat:useagentmode',
    ],

    // MBS-10761: Authorises a user to approve write-operations proposed by the tool-agent.
    // Without this capability, approval cards are hidden and approve/reject external functions fail.
    'block/ai_chat:approvewrite' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/ai_chat:useagent',
    ],

    // MBS-10761: Authorises session-scoped trust preferences ("Always allow in this session").
    'block/ai_chat:trustsession' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'block/ai_chat:useagent',
    ],
];
