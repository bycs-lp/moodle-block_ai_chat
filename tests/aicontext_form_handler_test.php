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

use block_ai_chat\local\aicontext_form_handler;

/**
 * Test class for aicontext_form_handler.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Umut Zerman
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_ai_chat\local\aicontext_form_handler
 */
final class aicontext_form_handler_test extends \advanced_testcase {

    /**
     * Ensures HTML tags are stripped from content when storing an aicontext record.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_store_form_data_strips_html_from_content(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $handler = new aicontext_form_handler();

        $data = (object) [
            'name' => 'Cloze test',
            'description' => 'Test description',
            'content' => "Curly braces must be escaped:<br /> * `{` becomes `\\{`<br /> * `}` becomes `\\}`<br /><br />Use valid Cloze syntax.",
            'enabled' => 1,
            'pagetypes' => '',
        ];

        $handler->store_form_data($data);

        $record = $DB->get_record('block_ai_chat_aicontext', ['name' => 'Cloze test'], '*', MUST_EXIST);

        $this->assertStringNotContainsString('<br />', $record->content);
        $this->assertStringNotContainsString('<', $record->content);
        $this->assertStringContainsString('Curly braces must be escaped', $record->content);
    }

    /**
     * Ensures plain-text content is stored unchanged (no stripping of non-HTML content).
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_store_form_data_preserves_plain_text_content(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $handler = new aicontext_form_handler();

        $data = (object) [
            'name' => 'Plain text context',
            'content' => "Use valid Cloze syntax.\nCurly braces must be escaped: \\{ and \\}",
            'enabled' => 1,
            'pagetypes' => '',
        ];

        $handler->store_form_data($data);

        $record = $DB->get_record('block_ai_chat_aicontext', ['name' => 'Plain text context'], '*', MUST_EXIST);

        $this->assertStringContainsString('Use valid Cloze syntax.', $record->content);
        $this->assertStringContainsString('\\{ and \\}', $record->content);
    }
}
