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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/blocks/ai_chat/db/upgradelib.php');

/**
 * Tests for AI Chat upgradelib functions.
 *
 * @package   block_ai_chat
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    ::block_ai_chat_cleanup_left_over_data
 */
final class upgradelib_test extends \advanced_testcase {
    /**
     * Test that block_ai_chat_cleanup_left_over_data removes orphaned records and keeps valid ones.
     *
     * @covers ::block_ai_chat_cleanup_left_over_data
     */
    public function test_block_ai_chat_cleanup_left_over_data(): void {
        global $DB;
        $this->resetAfterTest();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        // Create a course and block instance.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $blockinstance = $generator->create_instance([
            'parentcontextid' => $coursecontext->id,
            'pagetypepattern' => 'course-view-*',
        ]);
        $blockcontext = \context_block::instance($blockinstance->id);
        $persona = $generator->create_persona();
        $manager = new manager($blockcontext->id, 'block_ai_chat');
        $manager->select_persona($persona->id);

        // Create a valid record in block_ai_chat_options.
        $generator->create_option([
            'name' => 'testoption',
            'value' => 'testvalue',
            'contextid' => $blockcontext->id,
        ]);

        // Get a non-existing context id (orphaned context).
        $orphanedcontextid = $DB->get_field_sql('SELECT MAX(id) + 1000 FROM {context}');

        // Create an orphaned record in block_ai_chat_personas_selected.
        // We intentionally use direct DB insert here instead of manager->select_persona(),
        // because select_persona() requires a valid context, but we need an orphaned record with non-existing context.
        $orphanedselected = new \stdClass();
        $orphanedselected->personasid = $persona->id;
        $orphanedselected->contextid = $orphanedcontextid;
        $DB->insert_record('block_ai_chat_personas_selected', $orphanedselected);

        // Create an orphaned record in block_ai_chat_options using the generator.
        $generator->create_option([
            'name' => 'orphanedoption',
            'value' => 'orphanedvalue',
            'contextid' => $orphanedcontextid,
        ]);

        // Verify all records exist before cleanup.
        $this->assertEquals(2, $DB->count_records('block_ai_chat_personas_selected'));
        $this->assertEquals(2, $DB->count_records('block_ai_chat_options'));

        // Run the cleanup function.
        block_ai_chat_cleanup_left_over_data();

        // Verify orphaned records are removed and valid records still exist.
        $this->assertEquals(1, $DB->count_records('block_ai_chat_personas_selected'));
        $this->assertEquals(1, $DB->count_records('block_ai_chat_options'));

        // Verify the valid records still exist.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $blockcontext->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $blockcontext->id]));

        // Verify the orphaned records are removed.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $orphanedcontextid]));
        $this->assertFalse($DB->record_exists('block_ai_chat_options', ['contextid' => $orphanedcontextid]));

        // Test with empty tables - cleanup should handle gracefully (delete remaining records first).
        $DB->delete_records('block_ai_chat_personas_selected');
        $DB->delete_records('block_ai_chat_options');
        block_ai_chat_cleanup_left_over_data();
        $this->assertEquals(0, $DB->count_records('block_ai_chat_personas_selected'));
        $this->assertEquals(0, $DB->count_records('block_ai_chat_options'));
    }
}
