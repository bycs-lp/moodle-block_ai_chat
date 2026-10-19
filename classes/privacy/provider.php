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

namespace block_ai_chat\privacy;

use block_ai_chat\local\persona;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'block_ai_chat_personas',
            [
                'userid' => 'privacy:metadata:block_ai_chat_personas:userid',
                'name' => 'privacy:metadata:block_ai_chat_personas:name',
                'prompt' => 'privacy:metadata:block_ai_chat_personas:prompt',
                'userinfo' => 'privacy:metadata:block_ai_chat_personas:userinfo',
            ],
            'privacy:metadata:block_ai_chat_personas'
        );
        return $collection;
    }

    #[\Override]
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {block_ai_chat_personas} p ON p.userid = ctx.instanceid AND ctx.contextlevel = :contextlevel
                 WHERE p.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_USER, 'userid' => $userid]);
        return $contextlist;
    }

    #[\Override]
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_user) {
            return;
        }
        $sql = "SELECT userid FROM {block_ai_chat_personas} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
    }

    #[\Override]
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_user || $context->instanceid != $userid) {
                continue;
            }
            $personas = [];
            foreach ($DB->get_records('block_ai_chat_personas', ['userid' => $userid]) as $record) {
                $type = intval($record->type) === persona::TYPE_TEMPLATE ? 'typetemplate' : 'typeuser';
                $personas[] = (object) [
                    'name' => $record->name,
                    'prompt' => $record->prompt,
                    'userinfo' => $record->userinfo,
                    'type' => get_string($type, 'block_ai_chat'),
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }
            writer::with_context($context)->export_data(
                [
                    get_string('pluginname', 'block_ai_chat'),
                    get_string('privacy:personas', 'block_ai_chat'),
                ],
                (object) ['personas' => $personas]
            );
        }
    }

    #[\Override]
    public static function delete_data_for_all_users_in_context(\context $context): void {
        if ($context instanceof \context_user) {
            self::delete_user_personas($context->instanceid);
        }
    }

    #[\Override]
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_user && $context->instanceid == $userid) {
                self::delete_user_personas($userid);
            }
        }
    }

    #[\Override]
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context instanceof \context_user && in_array($context->instanceid, $userlist->get_userids())) {
            self::delete_user_personas($context->instanceid);
        }
    }

    /**
     * Delete the personal personas of a user and their selections in chat instances.
     *
     * Persona templates are shared site-wide and are kept.
     *
     * @param int $userid
     */
    private static function delete_user_personas(int $userid): void {
        global $DB;
        $personaids = $DB->get_fieldset('block_ai_chat_personas', 'id', ['userid' => $userid, 'type' => persona::TYPE_USER]);
        if (empty($personaids)) {
            return;
        }
        $DB->delete_records_list('block_ai_chat_personas_selected', 'personasid', $personaids);
        $DB->delete_records_list('block_ai_chat_personas', 'id', $personaids);
    }
}
