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

use block_ai_chat\local\persona;

/**
 * Tests for the select_persona external function.
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Dr. Peter Mayer
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class select_persona_test extends \advanced_testcase {
    /**
     * A teacher must not select the private persona of another user.
     *
     * @covers \block_ai_chat\external\select_persona::execute
     */
    public function test_select_foreign_persona_is_rejected(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $otheruser = $this->getDataGenerator()->create_user();
        $coursecontext = \context_course::instance($course->id);
        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $coursecontext->id]);
        $blockcontext = \context_block::instance($block->id);
        $foreignpersona = $this->getDataGenerator()->get_plugin_generator('block_ai_chat')->create_persona([
            'userid' => $otheruser->id,
            'type' => persona::TYPE_USER,
        ]);

        $this->setUser($teacher);
        try {
            select_persona::execute($blockcontext->id, 'block_ai_chat', $foreignpersona->id);
            $this->fail('Selecting a foreign private persona must be rejected.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error_viewpersonanotallowed', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $blockcontext->id]));
    }

    /**
     * A teacher can still select an own persona.
     *
     * @covers \block_ai_chat\external\select_persona::execute
     */
    public function test_select_own_persona(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $coursecontext = \context_course::instance($course->id);
        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $coursecontext->id]);
        $blockcontext = \context_block::instance($block->id);
        $ownpersona = $this->getDataGenerator()->get_plugin_generator('block_ai_chat')->create_persona([
            'userid' => $teacher->id,
            'type' => persona::TYPE_USER,
        ]);

        $this->setUser($teacher);
        select_persona::execute($blockcontext->id, 'block_ai_chat', $ownpersona->id);

        $this->assertEquals(
            $ownpersona->id,
            $DB->get_field('block_ai_chat_personas_selected', 'personasid', ['contextid' => $blockcontext->id])
        );
    }
}
