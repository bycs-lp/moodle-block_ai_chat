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

namespace block_ai_chat;

use block_ai_chat\local\persona;
use stored_file;

/**
 * Tests for AI Chat backup and restore operations.
 *
 * @package   block_ai_chat
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \backup_ai_chat_block_structure_step
 * @covers    \backup_ai_chat_block_task
 * @covers    \restore_ai_chat_block_structure_step
 * @covers    \restore_ai_chat_block_task
 */
final class backup_restore_test extends \advanced_testcase {
    /**
     * Setup test environment.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Data provider for test_restore_reuses_existing_persona test function.
     *
     * @return array
     */
    public static function restore_reuses_existing_persona_provider(): array {
        return [
            'with_userdata' => ['withuserdata' => true],
            'without_userdata' => ['withuserdata' => false],
        ];
    }

    /**
     * Test backup and restore of an existing persona with/without user data.
     * When backing up and restoring, the existing persona should be reused if it exists.
     *
     * @dataProvider restore_reuses_existing_persona_provider
     * @param bool $withuserdata Whether to include user data in backup
     */
    public function test_restore_reuses_existing_persona(bool $withuserdata): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        [$course, , $persona] = $this->create_course_with_block_and_persona($user);
        $personacountbeforerestore = $DB->count_records('block_ai_chat_personas');

        // Backup the course.
        $backupid = $this->perform_backup($course, $withuserdata);

        // Restore the course to a new course.
        $newcourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $newcourse->id, 'editingteacher');
        $newcourseid = $this->perform_restore($backupid, $user->id, $newcourse->id);

        // Check that no new persona was created. This should be independent of whether user data was included.
        $this->assertEquals($personacountbeforerestore, $DB->count_records('block_ai_chat_personas'));

        // Check that the block in the new course uses the same persona.
        $newcontextid = $this->get_block_contextid_in_course($newcourseid);
        $selected = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $newcontextid]);
        $this->assertEquals($persona->id, $selected->personasid);
    }

    /**
     * Test restore where original persona is missing creates new one owned by restorer.
     */
    public function test_restore_missing_persona_creates_new_owned_by_restorer(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        [$course, , $persona] = $this->create_course_with_block_and_persona($user);

        // Backup with user data.
        $backupid = $this->perform_backup($course, true);

        // Delete the original persona.
        $DB->delete_records('block_ai_chat_personas', ['id' => $persona->id]);
        $DB->delete_records('block_ai_chat_personas_selected', ['personasid' => $persona->id]);

        // Restore as User (same user).
        $newcourse = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $newcourse->id, 'editingteacher');
        $personacountbeforerestore = $DB->count_records('block_ai_chat_personas');
        $personaidsbeforerestore = $DB->get_fieldset('block_ai_chat_personas', 'id');
        $this->setUser($user);
        $this->perform_restore($backupid, $user->id, $newcourse->id);

        // Check that a new persona was created.
        $this->assertEquals($personacountbeforerestore + 1, $DB->count_records('block_ai_chat_personas'));
        [$insql, $inparams] = $DB->get_in_or_equal($personaidsbeforerestore, SQL_PARAMS_NAMED, 'param', false);
        $newpersona = array_values($DB->get_records_select('block_ai_chat_personas', "id $insql", $inparams))[0];

        $this->assertNotEquals($persona->id, $newpersona->id);
        $this->assertEquals($user->id, $newpersona->userid);
    }

    /**
     * Data provider for whether to test as admin or regular user.
     *
     * @return array
     */
    public static function restore_always_creates_private_persona_provider(): array {
        return [
            'admin_user_template' => [
                'usertype' => 'admin',
                'personatype' => persona::TYPE_TEMPLATE,
            ],
            'normal_user_template' => [
                'usertype' => 'user',
                'personatype' => persona::TYPE_TEMPLATE,
            ],
            'admin_user_private' => [
                'usertype' => 'admin',
                'personatype' => persona::TYPE_USER,
            ],
            'normal_user_private' => [
                'usertype' => 'user',
                'personatype' => persona::TYPE_USER,
            ],
        ];
    }

    /**
     * Test restore creates private persona instead of system template.
     *
     * @dataProvider restore_always_creates_private_persona_provider
     * @param string $usertype The type of user performing the restore
     * @param int $personatype The type of persona being backed up
     */
    public function test_restore_always_creates_private_persona(string $usertype, int $personatype): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();
        $this->setAdminUser();
        // Create course with a persona that is NOT a system template (user type).
        [$course, , $persona] =
            $this->create_course_with_block_and_persona($admin, ['type' => $personatype]);

        // Backup without user data.
        $backupid = $this->perform_backup($course, false);
        // Delete persona. Otherwise, it will be linked instead of newly created.
        $DB->delete_records('block_ai_chat_personas', ['id' => $persona->id]);
        $DB->delete_records('block_ai_chat_personas_selected', ['personasid' => $persona->id]);

        // Setup the user who restores.
        $newcourse = $this->getDataGenerator()->create_course();

        if ($usertype === 'admin') {
            $restoringuser = $admin;
        } else {
            $restoringuser = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($restoringuser->id, $newcourse->id, 'editingteacher');
        }

        $this->setUser($restoringuser);

        // Restore.
        $newcourseid = $this->perform_restore($backupid, $restoringuser->id, $newcourse->id);

        // Check that the NEW persona is TYPE_USER, not TYPE_TEMPLATE.
        // Identify the new persona.
        $newcontextid = $this->get_block_contextid_in_course($newcourseid);
        $selected = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $newcontextid]);
        $newpersona = $DB->get_record('block_ai_chat_personas', ['id' => $selected->personasid]);

        $this->assertNotEquals($persona->id, $newpersona->id);

        $this->assertEquals(persona::TYPE_USER, (int) $newpersona->type);
        $this->assertEquals($restoringuser->id, $newpersona->userid);
    }

    /**
     * Helper to create course, block and persona.
     * @param \stdClass $user
     * @param array $options
     * @return array [$course, $block, $persona]
     */
    private function create_course_with_block_and_persona($user, $options = []): array {
        global $DB;
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $block = $generator->create_block('ai_chat', ['parentcontextid' => \context_course::instance($course->id)->id]);

        $pgenerator = $generator->get_plugin_generator('block_ai_chat');
        $persona = $pgenerator->create_persona(array_merge(['userid' => $user->id], $options));

        $context = \context_block::instance($block->id);
        // Unset old selection if any (default generator might not select anything).
        $DB->delete_records('block_ai_chat_personas_selected', ['contextid' => $context->id]);
        $record = (object) [
            'contextid' => $context->id,
            'personasid' => $persona->id,
        ];
        $DB->insert_record('block_ai_chat_personas_selected', $record);

        return [$course, $block, $persona];
    }

    /**
     * Helper to perform backup.
     * @param \stdClass $course
     * @param bool $withuserdata
     * @return stored_file
     */
    private function perform_backup(\stdClass $course, bool $withuserdata): stored_file {
        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            get_admin()->id
        );
        // Set users setting.
        if ($bc->get_plan()->setting_exists('users')) {
            $bc->get_plan()->get_setting('users')->set_value($withuserdata);
        }
        $bc->execute_plan();
        $results = $bc->get_results();
        $file = $results['backup_destination'];
        $bc->destroy();
        return $file;
    }

    /**
     * Helper to perform restore.
     * @param stored_file $file
     * @param int $userid
     * @param int $targetcourseid
     * @return int New course id
     */
    private function perform_restore($file, $userid, $targetcourseid): int {
        // We need a unique directory for restore.
        $restoreid = uniqid('restore_');
        $backupdir = make_backup_temp_directory($restoreid);
        $file->extract_to_pathname(get_file_packer('application/vnd.moodle.backup'), $backupdir);

        $rc = new \restore_controller(
            $restoreid,
            $targetcourseid,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $userid,
            \backup::TARGET_EXISTING_ADDING
        );

        $rc->execute_precheck();
        $rc->execute_plan();
        $courseid = $rc->get_courseid();
        $rc->destroy();
        return $courseid;
    }

    /**
     * Helper to get block context id in a course.
     * @param int $courseid
     * @return int context id
     */
    protected function get_block_contextid_in_course($courseid): int {
        global $DB;
        $coursecontext = \context_course::instance($courseid);
        $block = $DB->get_record(
            'block_instances',
            [
                'parentcontextid' => $coursecontext->id,
                'blockname' => 'ai_chat',
            ],
            '*',
            MUST_EXIST,
        );
        return \context_block::instance($block->id)->id;
    }
}
