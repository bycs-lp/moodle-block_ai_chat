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

/**
 * Test class for the manager class of block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class manager_test extends \advanced_testcase {
    /**
     * Ensures edit_persona updates the record and sanitizes the returned payload.
     *
     * @covers \block_ai_chat\manager::edit_persona
     */
    public function test_edit_persona(): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $coursecontext->id]);
        $blockcontext = \context_block::instance($block->id);

        $manager = new manager($blockcontext->id, 'block_ai_chat');
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        $persona = $generator->create_persona([
            'userid' => $USER->id,
            'name' => 'Original Persona',
            'prompt' => 'Original prompt',
            'userinfo' => 'Original info',
            'type' => persona::TYPE_USER,
        ]);

        $data = (object) [
            'id' => $persona->id,
            'userid' => $USER->id,
            'name' => 'Updated Persona',
            'prompt' => '<script>alert("prompt")</script> Updated prompt',
            'userinfo' => '<script>alert("info")</script> Updated info',
            'type' => persona::TYPE_TEMPLATE,
        ];

        $response = $manager->edit_persona($data, $USER->id);

        $this->assertSame(200, $response['code']);
        $this->assertCount(1, $response['content']);

        $updateentry = $response['content'][0];
        $this->assertSame('personas', $updateentry['name']);
        $this->assertSame('update', $updateentry['action']);

        $returnedpersona = json_decode($updateentry['fields']);
        $this->assertSame($data->name, $returnedpersona->name);
        $this->assertSame($data->userid, $returnedpersona->userid);
        $this->assertSame(persona::TYPE_TEMPLATE, (int) $returnedpersona->type);
        $this->assertStringNotContainsString('<script>', $returnedpersona->prompt);
        $this->assertStringNotContainsString('<script>', $returnedpersona->userinfo);

        $dbpersona = $DB->get_record('block_ai_chat_personas', ['id' => $persona->id], '*', MUST_EXIST);
        $this->assertSame($data->name, $dbpersona->name);
        $this->assertSame($data->prompt, $dbpersona->prompt);
        $this->assertSame($data->userinfo, $dbpersona->userinfo);
        $this->assertSame(persona::TYPE_TEMPLATE, (int) $dbpersona->type);
    }

    /**
     * Ensures edit_persona enforces its access checks.
     *
     * @covers \block_ai_chat\manager::edit_persona
     */
    public function test_edit_persona_access_checks(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $coursecontext->id]);
        $blockcontext = \context_block::instance($block->id);

        $manager = new manager($blockcontext->id, 'block_ai_chat');
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        $userwithoutcap = $this->getDataGenerator()->create_user();

        $templatepersona = $generator->create_persona([
            'userid' => get_admin()->id,
            'name' => 'Template Persona',
            'prompt' => 'Template prompt',
            'userinfo' => 'Template info',
            'type' => persona::TYPE_TEMPLATE,
        ]);

        $templateupdate = (object) [
            'id' => $templatepersona->id,
            'userid' => $templatepersona->userid,
            'name' => 'Updated Template',
            'prompt' => 'Updated prompt',
            'userinfo' => 'Updated info',
            'type' => persona::TYPE_TEMPLATE,
        ];

        try {
            $manager->edit_persona($templateupdate, $userwithoutcap->id);
            $this->fail('Capability check should block access without permission.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }

        $userwithcap = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role(['shortname' => 'aichatmanager', 'name' => 'AI Chat Manager']);
        $this->getDataGenerator()->role_assign($roleid, $userwithcap->id, $blockcontext->id);
        assign_capability('block/ai_chat:managepersonatemplates', CAP_ALLOW, $roleid, $blockcontext->id);

        try {
            $manager->edit_persona($templateupdate, $userwithcap->id);
        } catch (\Throwable $e) {
            $this->fail('User with capability "block/ai_chat:managepersonatemplates" should be allowed: ' . $e->getMessage());
        }

        $userpersona = $generator->create_persona([
            'userid' => $userwithcap->id,
            'name' => 'Owned Persona',
            'prompt' => 'Owned prompt',
            'userinfo' => 'Owned info',
            'type' => persona::TYPE_USER,
        ]);

        $conversionrequest = (object) [
            'id' => $userpersona->id,
            'userid' => $userwithcap->id,
            'name' => 'Owned Persona Updated',
            'prompt' => 'Updated owned prompt',
            'userinfo' => 'Updated owned info',
            'type' => persona::TYPE_TEMPLATE,
        ];

        try {
            $manager->edit_persona($conversionrequest, $userwithcap->id);
        } catch (\Throwable $e) {
            $this->fail('Unexpected exception: ' . $e->getMessage());
        }

        // Test: User without capability cannot convert personal persona to template.
        // In theory already covered before, because it's basically just the same as
        // editing a template, but for it's a separate use case, so we test it explicitly.
        $personalpersona = $generator->create_persona([
            'userid' => $userwithoutcap->id,
            'name' => 'Personal Persona',
            'prompt' => 'Personal prompt',
            'userinfo' => 'Personal info',
            'type' => persona::TYPE_USER,
        ]);

        $illegalconversion = (object) [
            'id' => $personalpersona->id,
            'userid' => $userwithoutcap->id,
            'name' => 'Converted Persona',
            'prompt' => 'Converted prompt',
            'userinfo' => 'Converted info',
            'type' => persona::TYPE_TEMPLATE,
        ];

        $exceptionthrown = false;
        try {
            $manager->edit_persona($illegalconversion, $userwithoutcap->id);
        } catch (\Throwable $e) {
            $exceptionthrown = true;
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }
        $this->assertTrue($exceptionthrown, 'User without capability should not be able to convert personal persona to template');
    }

    /**
     * Test for retrieving the correct personas.
     *
     * @covers \block_ai_chat\manager::get_personas
     * @covers \block_ai_chat\local\persona::get_all_personas
     */
    public function test_get_personas(): void {
        global $DB;
        $this->resetAfterTest();
        // Delete global personas that have been installed to get a clean database before test.
        $DB->delete_records('block_ai_chat_personas');

        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);
        $user2 = $this->getDataGenerator()->create_user();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $context->id]);
        $blockcontext = \context_block::instance($block->id);

        $manager = new manager($blockcontext->id, 'block_ai_chat');
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        $generator->create_persona([
            'userid' => $user1->id,
            'name' => 'User Persona',
            'prompt' => '<script>alert("prompt")</script>',
            'userinfo' => '<script>alert("userinfo")</script>',
            'type' => persona::TYPE_USER,
        ]);

        $selectedpersona = $generator->create_persona([
            'userid' => $user2->id,
            'name' => 'Selected Persona',
            'prompt' => 'Selected prompt',
            'userinfo' => 'Selected info',
            'type' => persona::TYPE_USER,
        ]);

        $templatepersona = $generator->create_persona([
            'userid' => get_admin()->id,
            'name' => 'Global Template',
            'prompt' => '<b>Template prompt</b>',
            'userinfo' => '<b>Template userinfo</b>',
            'type' => persona::TYPE_TEMPLATE,
        ]);

        $manager->select_persona($selectedpersona->id);

        $personas = $manager->get_personas($user1->id);

        $this->assertCount(3, $personas);
        $personaids = array_column($personas, 'id');
        $this->assertContains($selectedpersona->id, array_map('intval', $personaids));
        $this->assertContains($templatepersona->id, array_map('intval', $personaids));
        foreach ($personas as $personaentry) {
            $this->assertStringNotContainsString('<script>', $personaentry->prompt);
            $this->assertStringNotContainsString('<script>', $personaentry->userinfo);
        }

        // Test if the selected persona is not contained if there is no selected persona.
        $manager->select_persona(0);
        $personaswithoutselection = $manager->get_personas($user1->id);

        $this->assertCount(2, $personaswithoutselection);
        $personaids = array_column($personaswithoutselection, 'id');
        $this->assertContains($templatepersona->id, array_map('intval', $personaids));
        foreach ($personaswithoutselection as $personaentry) {
            $this->assertStringNotContainsString('<script>', $personaentry->prompt);
            $this->assertStringNotContainsString('<script>', $personaentry->userinfo);
        }
    }

    /**
     * Test the require_manage_persona method.
     *
     * @covers \block_ai_chat\manager::require_manage_persona
     */
    public function test_require_manage_persona(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Create test users.
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $admin = get_admin();

        // Create a course and context.
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        // Create block instance.
        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $context->id]);
        $blockcontext = \context_block::instance($block->id);

        $manager = new manager($blockcontext->id, 'block_ai_chat');
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        // Test 1: Non-existent persona - should not throw exception.
        try {
            $manager->require_manage_persona(99999, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('Non-existent persona should not throw exception: ' . $e->getMessage());
        }

        // Test 2: Admin can manage any user persona.
        $this->setUser($admin);
        $userpersona = $generator->create_persona([
            'userid' => $user1->id,
            'name' => 'Test User Persona',
            'type' => \block_ai_chat\local\persona::TYPE_USER,
        ]);
        try {
            $manager->require_manage_persona($userpersona->id, $admin->id);
        } catch (\moodle_exception $e) {
            $this->fail('Admin should be able to manage any user persona: ' . $e->getMessage());
        }

        // Test 3: User with capability can manage template personas.
        $templatepersona = $generator->create_persona([
            'userid' => $admin->id,
            'name' => 'Template Persona',
            'type' => \block_ai_chat\local\persona::TYPE_TEMPLATE,
        ]);
        $this->setUser($user1);

        // Give user the capability to manage template personas.
        $role = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $this->getDataGenerator()->role_assign($role->id, $user1->id, $blockcontext->id);
        assign_capability('block/ai_chat:managepersonatemplates', CAP_ALLOW, $role->id, $blockcontext->id);

        try {
            $manager->require_manage_persona($templatepersona->id, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('User with capability should be able to manage template personas: ' . $e->getMessage());
        }

        // Test 4: Owner can manage their own persona.
        $this->setUser($user1);
        $ownpersona = $generator->create_persona([
            'userid' => $user1->id,
            'name' => 'Own Persona',
            'type' => \block_ai_chat\local\persona::TYPE_USER,
        ]);
        try {
            $manager->require_manage_persona($ownpersona->id, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('Owner should be able to manage their own persona: ' . $e->getMessage());
        }

        // Test 5: User without capability cannot manage template personas.
        $this->setUser($user2);
        $exceptionthrown = false;
        try {
            $manager->require_manage_persona($templatepersona->id, $user2->id);
        } catch (\moodle_exception $e) {
            $exceptionthrown = true;
            $this->assertStringContainsString('nopermissions', $e->errorcode);
        }
        $this->assertTrue($exceptionthrown, 'User without capability should not be able to manage template personas');

        // Test 6: User cannot manage another user's persona.
        $this->setUser($user2);
        $exceptionthrown = false;
        try {
            $manager->require_manage_persona($ownpersona->id, $user2->id);
        } catch (\moodle_exception $e) {
            $exceptionthrown = true;
            $this->assertStringContainsString(get_string('error_managepersonanotallowed', 'block_ai_chat'), $e->getMessage());
        }
        $this->assertTrue($exceptionthrown, 'User should not be able to manage another user\'s persona');
    }

    /**
     * Test the require_manage_persona method.
     *
     * @covers \block_ai_chat\manager::require_manage_persona
     */
    public function test_require_view_persona(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $admin = get_admin();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);

        $block = $this->getDataGenerator()->create_block('ai_chat', ['parentcontextid' => $context->id]);
        $blockcontext = \context_block::instance($block->id);

        $manager = new manager($blockcontext->id, 'block_ai_chat');
        $generator = $this->getDataGenerator()->get_plugin_generator('block_ai_chat');

        // Test 1: Non-existent persona - should not throw exception.
        try {
            $manager->require_view_persona(99999, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('Non-existent persona should not throw exception: ' . $e->getMessage());
        }

        // Test 2: Admin can view any persona.
        $this->setUser($admin);
        $userpersona = $generator->create_persona([
            'userid' => $user1->id,
            'name' => 'Test User Persona',
            'type' => \block_ai_chat\local\persona::TYPE_USER,
        ]);
        try {
            $manager->require_view_persona($userpersona->id, $admin->id);
        } catch (\moodle_exception $e) {
            $this->fail('Admin should be able to view any persona: ' . $e->getMessage());
        }

        // Test 3: Template personas can be viewed by everyone.
        $templatepersona = $generator->create_persona([
            'userid' => $admin->id,
            'name' => 'Template Persona',
            'type' => \block_ai_chat\local\persona::TYPE_TEMPLATE,
        ]);
        $this->setUser($user1);
        try {
            $manager->require_view_persona($templatepersona->id, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('Template persona should be viewable by any user: ' . $e->getMessage());
        }

        // Test 4: Owner can view their own persona.
        $this->setUser($user1);
        try {
            $manager->require_view_persona($userpersona->id, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('Owner should be able to view their own persona: ' . $e->getMessage());
        }

        // Test 5: Selected persona in current context can be viewed.
        $otherpersona = $generator->create_persona([
            'userid' => $user2->id,
            'name' => 'Other User Persona',
            'type' => \block_ai_chat\local\persona::TYPE_USER,
        ]);
        $DB->insert_record('block_ai_chat_personas_selected', [
            'contextid' => $blockcontext->id,
            'personasid' => $otherpersona->id,
        ]);
        $this->setUser($user1);
        try {
            $manager->require_view_persona($otherpersona->id, $user1->id);
        } catch (\moodle_exception $e) {
            $this->fail('Selected persona in current context should be viewable: ' . $e->getMessage());
        }

        // Test 6: User cannot view another user's unselected persona.
        $this->setUser($user1);
        $restrictedpersona = $generator->create_persona([
            'userid' => $user2->id,
            'name' => 'Restricted Persona',
            'type' => \block_ai_chat\local\persona::TYPE_USER,
        ]);

        $exceptionthrown = false;
        try {
            $manager->require_view_persona($restrictedpersona->id, $user1->id);
        } catch (\moodle_exception $e) {
            $exceptionthrown = true;
            $this->assertStringContainsString(get_string('error_viewpersonanotallowed', 'block_ai_chat'), $e->getMessage());
        }
        $this->assertTrue($exceptionthrown, 'Exception should be thrown for unauthorized persona access');
    }
}
