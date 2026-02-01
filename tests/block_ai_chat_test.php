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

/**
 * Tests for AI Chat
 *
 * @package   block_ai_chat
 * @copyright 2026 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_ai_chat
 */
final class block_ai_chat_test extends \advanced_testcase {
    /**
     * Test deleting a block instance.
     *
     * This test verifies that when a block instance is deleted, the corresponding
     * entries in the database are being properly removed.
     *
     * @covers \block_ai_chat::instance_delete
     */
    public function test_instance_delete(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        /** @var \block_ai_chat_generator $blockgenerator */
        $blockgenerator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        // Create two block instances in the course.
        $block1record = $blockgenerator->create_instance([
            'parentcontextid' => $coursecontext->id,
            'pagetypepattern' => 'course-view-*',
        ]);
        $block2record = $blockgenerator->create_instance([
            'parentcontextid' => $coursecontext->id,
            'pagetypepattern' => 'course-view-*',
        ]);

        // Get block contexts.
        $block1context = \context_block::instance($block1record->id);
        $block2context = \context_block::instance($block2record->id);

        // Create two personas.
        $persona1 = $blockgenerator->create_persona(['name' => 'Test Persona 1']);
        $persona2 = $blockgenerator->create_persona(['name' => 'Test Persona 2']);

        // Link persona 1 to block 1.
        $manager1 = new manager($block1context->id, 'block_ai_chat');
        $manager1->select_persona($persona1->id);

        // Link persona 2 to block 2.
        $manager2 = new manager($block2context->id, 'block_ai_chat');
        $manager2->select_persona($persona2->id);

        // Create options for both blocks.
        $DB->insert_record('block_ai_chat_options', [
            'name' => 'historycontextmax',
            'value' => '5',
            'contextid' => $block1context->id,
        ]);
        $DB->insert_record('block_ai_chat_options', [
            'name' => 'historycontextmax',
            'value' => '10',
            'contextid' => $block2context->id,
        ]);

        // Verify both persona selections exist before deletion.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $block1context->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $block2context->id]));
        $this->assertEquals(2, $DB->count_records('block_ai_chat_personas_selected'));

        // Verify both options exist before deletion.
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $block1context->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $block2context->id]));
        $this->assertEquals(2, $DB->count_records('block_ai_chat_options'));

        // Create log entries for both blocks using the local_ai_manager generator.
        /** @var \local_ai_manager_generator $aimanagergenerator */
        $aimanagergenerator = $this->getDataGenerator()->get_plugin_generator('local_ai_manager');

        $logentry1 = $aimanagergenerator->create_request_log_entry([
            'component' => 'block_ai_chat',
            'contextid' => $block1context->id,
            'value' => 100,
            'deleted' => 0,
        ]);

        $logentry2 = $aimanagergenerator->create_request_log_entry([
            'component' => 'block_ai_chat',
            'contextid' => $block2context->id,
            'value' => 200,
            'deleted' => 0,
        ]);

        // Verify both log entries exist and are not deleted before deletion using ai_manager_utils.
        $logentriesblock1 = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $block1context->id);
        $this->assertCount(1, $logentriesblock1);
        $logentry1retrieved = reset($logentriesblock1);
        $this->assertEquals($logentry1->id, $logentry1retrieved->id);
        $this->assertEquals(0, $logentry1retrieved->deleted);

        $logentriesblock2 = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $block2context->id);
        $this->assertCount(1, $logentriesblock2);
        $logentry2retrieved = reset($logentriesblock2);
        $this->assertEquals($logentry2->id, $logentry2retrieved->id);
        $this->assertEquals(0, $logentry2retrieved->deleted);

        // Delete block 1.
        blocks_delete_instance($DB->get_record('block_instances', ['id' => $block1record->id]));

        // Verify that the persona selection for block 1 is deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $block1context->id]));
        // Verify that the persona selection for block 2 still exists.
        $this->assertTrue($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $block2context->id]));
        $this->assertEquals(1, $DB->count_records('block_ai_chat_personas_selected'));
        // Verify the remaining record has the correct persona assigned.
        $remainingrecord = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $block2context->id]);
        $this->assertEquals($persona2->id, $remainingrecord->personasid);

        // Verify that the options for block 1 are deleted.
        $this->assertFalse($DB->record_exists('block_ai_chat_options', ['contextid' => $block1context->id]));
        // Verify that the options for block 2 still exist.
        $this->assertTrue($DB->record_exists('block_ai_chat_options', ['contextid' => $block2context->id]));
        $this->assertEquals(1, $DB->count_records('block_ai_chat_options'));
        // Verify the remaining option has the correct value.
        $remainingoption = $DB->get_record('block_ai_chat_options', ['contextid' => $block2context->id]);
        $this->assertEquals('10', $remainingoption->value);

        // Verify that the log entry for block 1 is marked as deleted using ai_manager_utils.
        $logentriesblock1afterdelete = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $block1context->id);
        $this->assertCount(1, $logentriesblock1afterdelete);
        $logentry1afterdelete = reset($logentriesblock1afterdelete);
        $this->assertEquals($logentry1->id, $logentry1afterdelete->id);
        $this->assertEquals(1, $logentry1afterdelete->deleted);

        // Verify that the log entry for block 2 is still not deleted using ai_manager_utils.
        $logentriesblock2afterdelete = \local_ai_manager\ai_manager_utils::get_log_entries('block_ai_chat', $block2context->id);
        $this->assertCount(1, $logentriesblock2afterdelete);
        $logentry2afterdelete = reset($logentriesblock2afterdelete);
        $this->assertEquals($logentry2->id, $logentry2afterdelete->id);
        $this->assertEquals(0, $logentry2afterdelete->deleted);
    }
}
