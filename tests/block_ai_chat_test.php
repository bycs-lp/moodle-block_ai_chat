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
        $manager1 = new manager($block1context->id);
        $manager1->select_persona($persona1->id);

        // Link persona 2 to block 2.
        $manager2 = new manager($block2context->id);
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
    }
}
