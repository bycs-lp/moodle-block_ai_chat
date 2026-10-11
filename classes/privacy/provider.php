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

use context;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_ai_chat.
 *
 * The AI chat conversations themselves are stored and handled by local_ai_manager. This plugin only stores
 * user created personas in the block_ai_chat_personas table, which is covered here.
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

    /**
     * Returns metadata about this plugin's user data.
     *
     * @param collection $collection the collection to add metadata to
     * @return collection the updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_ai_chat_personas', [
            'userid' => 'privacy:metadata:block_ai_chat_personas:userid',
            'name' => 'privacy:metadata:block_ai_chat_personas:name',
            'prompt' => 'privacy:metadata:block_ai_chat_personas:prompt',
            'userinfo' => 'privacy:metadata:block_ai_chat_personas:userinfo',
            'type' => 'privacy:metadata:block_ai_chat_personas:type',
            'timecreated' => 'privacy:metadata:block_ai_chat_personas:timecreated',
            'timemodified' => 'privacy:metadata:block_ai_chat_personas:timemodified',
        ], 'privacy:metadata:block_ai_chat_personas');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the user to search
     * @return contextlist the contexts containing data for the user
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;
        $contextlist = new contextlist();
        if ($DB->record_exists('block_ai_chat_personas', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }
        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist the userlist to add the users to
     */
    public static function get_users_in_context(userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        $sql = "SELECT DISTINCT userid
                  FROM {block_ai_chat_personas}";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts to export information for
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_system) {
                continue;
            }

            $records = $DB->get_records('block_ai_chat_personas', ['userid' => $userid], 'id ASC');
            if (empty($records)) {
                continue;
            }

            $personas = [];
            foreach ($records as $record) {
                $personas[] = (object) [
                    'name' => $record->name,
                    'prompt' => $record->prompt,
                    'userinfo' => $record->userinfo,
                    'type' => $record->type,
                    'timecreated' => transform::datetime($record->timecreated),
                    'timemodified' => transform::datetime($record->timemodified),
                ];
            }

            writer::with_context($context)->export_data([
                get_string('pluginname', 'block_ai_chat'),
                'personas',
            ], (object) ['personas' => $personas]);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param context $context the context to delete data in
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        global $DB;
        if (!$context instanceof context_system) {
            return;
        }
        $DB->delete_records('block_ai_chat_personas_selected');
        $DB->delete_records('block_ai_chat_personas');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist the approved contexts and user to delete information for
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_system) {
                static::delete_data_for_userid($contextlist->get_user()->id);
            }
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist the approved context and user information to delete information for
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        if (!$userlist->get_context() instanceof context_system) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            static::delete_data_for_userid($userid);
        }
    }

    /**
     * Delete persona data related to a userid.
     *
     * @param int $userid the id of the user whose personas should be deleted
     */
    protected static function delete_data_for_userid(int $userid): void {
        global $DB;
        $personaids = $DB->get_fieldset('block_ai_chat_personas', 'id', ['userid' => $userid]);
        if (!empty($personaids)) {
            [$insql, $params] = $DB->get_in_or_equal($personaids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('block_ai_chat_personas_selected', "personasid $insql", $params);
        }
        $DB->delete_records('block_ai_chat_personas', ['userid' => $userid]);
    }
}
