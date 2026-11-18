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
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Unit tests for the privacy provider of block_ai_chat.
 *
 * @package    block_ai_chat
 * @category   test
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_ai_chat\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass Owner of a user persona and a template. */
    private \stdClass $owner;

    /** @var \stdClass Owner of another user persona. */
    private \stdClass $other;

    /** @var \stdClass User persona of the owner, selected in a chat. */
    private \stdClass $userpersona;

    /** @var \stdClass Template created by the owner. */
    private \stdClass $template;

    /** @var \stdClass User persona of the other user. */
    private \stdClass $otherpersona;

    #[\Override]
    protected function setUp(): void {
        global $DB;
        parent::setUp();
        $this->resetAfterTest();
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $this->owner = $this->getDataGenerator()->create_user();
        $this->other = $this->getDataGenerator()->create_user();
        $this->userpersona = $generator->create_persona(['userid' => $this->owner->id, 'name' => 'Own persona']);
        $this->template = $generator->create_persona([
            'userid' => $this->owner->id,
            'name' => 'Own template',
            'type' => persona::TYPE_TEMPLATE,
        ]);
        $this->otherpersona = $generator->create_persona(['userid' => $this->other->id]);
        $coursecontext = \context_course::instance($this->getDataGenerator()->create_course()->id);
        $DB->insert_record(
            'block_ai_chat_personas_selected',
            ['personasid' => $this->userpersona->id, 'contextid' => $coursecontext->id]
        );
    }

    /**
     * The personas table is described.
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('block_ai_chat'))->get_collection();
        $this->assertCount(1, $items);
        $this->assertEquals('block_ai_chat_personas', reset($items)->get_name());
    }

    /**
     * The user context of a persona owner is found, the owner is found in it.
     */
    public function test_get_contexts_and_users(): void {
        $usercontext = \context_user::instance($this->owner->id);
        $this->assertEquals([$usercontext->id], provider::get_contexts_for_userid($this->owner->id)->get_contextids());
        $nopersonas = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($nopersonas->id)->get_contextids());

        $userlist = new userlist($usercontext, 'block_ai_chat');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$this->owner->id], $userlist->get_userids());
    }

    /**
     * Personal personas and templates of the user are exported.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_export_user_data(): void {
        $usercontext = \context_user::instance($this->owner->id);
        $this->export_context_data_for_user($this->owner->id, $usercontext, 'block_ai_chat');

        $data = writer::with_context($usercontext)->get_data([
            get_string('pluginname', 'block_ai_chat'),
            get_string('privacy:personas', 'block_ai_chat'),
        ]);
        $this->assertEqualsCanonicalizing(['Own persona', 'Own template'], array_column($data->personas, 'name'));
    }

    /**
     * Only the personal personas of the user and their selections are deleted.
     */
    #[\PHPUnit\Framework\Attributes\Group('baseline')]
    public function test_delete_data_for_user(): void {
        $usercontext = \context_user::instance($this->owner->id);
        provider::delete_data_for_user(new approved_contextlist($this->owner, 'block_ai_chat', [$usercontext->id]));
        $this->assert_only_own_user_persona_deleted();
    }

    /**
     * Deleting a user list in the user context deletes only the personal personas of the user.
     */
    public function test_delete_data_for_users(): void {
        $usercontext = \context_user::instance($this->owner->id);
        provider::delete_data_for_users(new approved_userlist($usercontext, 'block_ai_chat', [$this->owner->id]));
        $this->assert_only_own_user_persona_deleted();
    }

    /**
     * Deleting all data in the user context deletes only the personal personas of the user.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        provider::delete_data_for_all_users_in_context(\context_user::instance($this->owner->id));
        $this->assert_only_own_user_persona_deleted();
    }

    /**
     * Assert that the user persona of the owner and its selection are gone, while all other personas are kept.
     */
    private function assert_only_own_user_persona_deleted(): void {
        global $DB;
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['id' => $this->userpersona->id]));
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['personasid' => $this->userpersona->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas', ['id' => $this->template->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas', ['id' => $this->otherpersona->id]));
    }
}
