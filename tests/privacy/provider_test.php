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

use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider tests for block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_ai_chat\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Test that the personas table is declared in the metadata.
     */
    public function test_get_metadata(): void {
        $collection = new collection('block_ai_chat');
        $metadata = provider::get_metadata($collection);
        $tables = array_map(fn($item) => $item->get_name(), $metadata->get_collection());
        $this->assertContains('block_ai_chat_personas', $tables);
    }

    /**
     * Test that the system context is returned for a user who owns personas.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $generator->create_persona(['userid' => $user->id]);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);
        $this->assertInstanceOf(context_system::class, $contextlist->current());

        $other = $this->getDataGenerator()->create_user();
        $this->assertCount(0, provider::get_contexts_for_userid($other->id));
    }

    /**
     * Test that users owning personas are returned for the system context.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $generator->create_persona(['userid' => $user->id]);

        $userlist = new userlist(context_system::instance(), 'block_ai_chat');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$user->id], $userlist->get_userids());
    }

    /**
     * Test that a user's personas are exported.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $generator->create_persona(['userid' => $user->id, 'name' => 'My persona']);

        $context = context_system::instance();
        $this->export_context_data_for_user($user->id, $context, 'block_ai_chat');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());
    }

    /**
     * Test that deleting all data for the context removes all personas.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $generator->create_persona(['userid' => $user->id]);

        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['userid' => $user->id]));
    }

    /**
     * Test that deletion for a single user also removes its selected references and spares other users.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $persona1 = $generator->create_persona(['userid' => $user1->id]);
        $generator->create_persona(['userid' => $user2->id]);
        $DB->insert_record('block_ai_chat_personas_selected',
            (object) ['personasid' => $persona1->id, 'contextid' => context_system::instance()->id]);

        $contextlist = new approved_contextlist($user1, 'block_ai_chat', [context_system::instance()->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['userid' => $user1->id]));
        $this->assertFalse($DB->record_exists('block_ai_chat_personas_selected', ['personasid' => $persona1->id]));
        $this->assertTrue($DB->record_exists('block_ai_chat_personas', ['userid' => $user2->id]));
    }

    /**
     * Test deletion of a specific set of users within the context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();

        /** @var \block_ai_chat_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');
        $generator->create_persona(['userid' => $user->id]);

        $approved = new approved_userlist(context_system::instance(), 'block_ai_chat', [$user->id]);
        provider::delete_data_for_users($approved);
        $this->assertFalse($DB->record_exists('block_ai_chat_personas', ['userid' => $user->id]));
    }
}
