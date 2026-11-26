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

/**
 * Test class for the block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      local_mbs
 * @group      mebis
 */

namespace block_ai_chat;

defined('MOODLE_INTERNAL') || die();

use core\di;
use core\hook\output\before_footer_html_generation;
use moodle_page;

global $CFG;
require_once($CFG->dirroot . '/lib/blocklib.php');
require_once($CFG->dirroot . '/course/edit_form.php');

/**
 * Test class for the block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Andreas Wagner
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(\block_ai_chat\local\hook_callbacks::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\block_ai_chat\local\helper::class)]
final class ai_chat_test extends \advanced_testcase {
    /**
     * Create a page with regions.
     *
     * @param array $regions
     * @param object $context
     * @param object $course
     * @param string $pagetype
     * @return moodle_page
     * @throws \coding_exception
     */
    protected function get_moodle_page(
        array $regions,
        object $context,
        object $course,
        string $pagetype
    ): \moodle_page {
        $page = new \moodle_page();
        $page->set_context($context);
        $page->set_course($course);
        $page->set_pagetype($pagetype);
        $page->set_url(new \moodle_url('/'));

        $page->blocks->add_regions($regions, false);
        $page->blocks->set_default_region($regions[0]);

        return $page;
    }

    /**
     * Tests the method get_addable_blocks
     *
     * @covers \block_ai_chat::applicable_formats
     */
    public function test_get_addable_blocks(): void {

        $this->setAdminUser();
        $this->resetAfterTest();

        // Create course containing a module.
        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);

        $options = ['course' => $course->id];
        $forum = $this->getDataGenerator()->create_module('forum', $options);
        $contextmodule = \context_module::instance($forum->cmid);

        // Get course page and module page.
        $coursepage = $this->get_moodle_page(
            ['side-pre'],
            $coursecontext,
            $course,
            'course-view'
        );

        $modulepage = $this->get_moodle_page(
            ['side-pre'],
            $contextmodule,
            $course,
            'mod-forum-view'
        );

        // Block cannot be added manually on course pages.
        $coursepage->blocks->load_blocks(false);
        $coursepage->blocks->create_all_block_instances();
        $this->assertFalse(in_array('ai_chat', array_keys($coursepage->blocks->get_addable_blocks())));

        $modulepage->blocks->load_blocks(false);
        $modulepage->blocks->create_all_block_instances();
        $this->assertFalse(in_array('ai_chat', array_keys($modulepage->blocks->get_addable_blocks())));
    }

    /**
     * Test hook before_footer.
     *
     * @return void
     * @throws \coding_exception
     * @throws \dml_exception
     *
     * @covers \hook_callbacks::handle_before_footer_html_generation
     */
    public function test_before_footer_html_generation_hook(): void {
        global $PAGE, $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Assert there is no general block instance existing.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertEmpty($aiblockinstances);

        // Replace the version of the manager in the DI container with a phpunit one.
        \core\di::set(
            \core\hook\manager::class,
            \core\hook\manager::phpunit_get_instance([
                'block_ai_chat' => $CFG->dirroot . '/blocks/ai_chat/db/hooks.php',
            ]),
        );

        // Prepare renderer.
        $PAGE->blocks->add_region('side-pre');
        $renderer = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);

        // Dispatch.
        $hook = new before_footer_html_generation($renderer);
        di::get(\core\hook\manager::class)->dispatch($hook);

        // Assert there is no general block instance existing.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertEmpty($aiblockinstances);

        // Set proper pagetype. We do not test the pagetype setting here, so we set it to "*" to make it show on all pages.
        set_config('showonpagetypes', '*', 'block_ai_chat');
        $configmanager = \core\di::get(\local_ai_manager\local\config_manager::class);
        $configmanager->set_config('tenantenabled', true);

        // Dispatch.
        ob_start();
        di::get(\core\hook\manager::class)->dispatch($hook);
        $output = ob_get_clean();

        // Assert block is printed.
        $this->assertStringContainsString('id="ai_chat_button"', $output);
        // Assert there is one general block instance existing.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertCount(1, $aiblockinstances);
        $aiblockinstance = array_shift($aiblockinstances);

        $this->assertEquals(\context_system::instance()->id, $aiblockinstance->parentcontextid);
        $this->assertEquals(0, $aiblockinstance->showinsubcontexts);
        $this->assertEquals('', $aiblockinstance->pagetypepattern);

        // Dispatch, while global block exists.
        ob_start();
        di::get(\core\hook\manager::class)->dispatch($hook);
        $output = ob_get_clean();

        // Assert there is no additional block instance created.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertCount(1, $aiblockinstances);
    }

    /**
     * Data provider for test_before_footer_html_generation_hook_without_regions.
     *
     * @return array Datasets with the pagetype pattern of the course block (null for no course block), whether the
     *  course block is hidden on the page and whether the course block is expected to be rendered.
     */
    public static function before_footer_html_generation_hook_without_regions_provider(): array {
        return [
            'course_block_matching' => ['pagetypepattern' => '*', 'hidden' => false, 'expectcourseblock' => true],
            'course_block_other_pagetype' => [
                'pagetypepattern' => 'course-view-*',
                'hidden' => false,
                'expectcourseblock' => false,
            ],
            'course_block_hidden' => ['pagetypepattern' => '*', 'hidden' => true, 'expectcourseblock' => false],
            'no_course_block' => ['pagetypepattern' => null, 'hidden' => false, 'expectcourseblock' => false],
        ];
    }

    /**
     * Test hook before_footer on a page without block regions (course/editsection.php in theme_boost).
     *
     * @param string|null $pagetypepattern the pagetype pattern of the course block, null for no course block
     * @param bool $hidden whether the course block is hidden on the page
     * @param bool $expectcourseblock whether the course block is expected to be rendered instead of the system instance
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('before_footer_html_generation_hook_without_regions_provider')]
    public function test_before_footer_html_generation_hook_without_regions(
        ?string $pagetypepattern,
        bool $hidden,
        bool $expectcourseblock
    ): void {
        global $PAGE, $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        \core\di::set(
            \core\hook\manager::class,
            \core\hook\manager::phpunit_get_instance([
                'block_ai_chat' => $CFG->dirroot . '/blocks/ai_chat/db/hooks.php',
            ]),
        );
        set_config('showonpagetypes', '*', 'block_ai_chat');
        \core\di::get(\local_ai_manager\local\config_manager::class)->set_config('tenantenabled', true);

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \context_course::instance($course->id);
        if ($pagetypepattern !== null) {
            // Course block as created by hook_callbacks::handle_after_form_submission().
            $courseblockid = $DB->insert_record('block_instances', [
                'blockname' => 'ai_chat',
                'parentcontextid' => $coursecontext->id,
                'showinsubcontexts' => 1,
                'pagetypepattern' => $pagetypepattern,
                'subpagepattern' => null,
                'defaultregion' => 'side-pre',
                'defaultweight' => 1,
                'configdata' => '',
                'timecreated' => \core\di::get(\core\clock::class)->time(),
                'timemodified' => \core\di::get(\core\clock::class)->time(),
            ]);
            \context_block::instance($courseblockid);
            if ($hidden) {
                $DB->insert_record('block_positions', [
                    'blockinstanceid' => $courseblockid,
                    'contextid' => $coursecontext->id,
                    'pagetype' => 'course-editsection',
                    'subpage' => '',
                    'visible' => 0,
                    'region' => 'side-pre',
                    'weight' => 1,
                ]);
            }
        }

        // Layout 'base' (default, not set by course/editsection.php) has no block regions in theme_boost.
        $PAGE->set_url(new \moodle_url('/course/editsection.php'));
        $PAGE->set_course($course);
        $PAGE->set_pagetype('course-editsection');
        $PAGE->force_theme('boost');
        $renderer = $PAGE->get_renderer('core', null, RENDERER_TARGET_GENERAL);
        $this->assertSame('base', $PAGE->pagelayout);
        $this->assertEmpty($PAGE->blocks->get_regions());

        ob_start();
        di::get(\core\hook\manager::class)->dispatch(new before_footer_html_generation($renderer));
        $output = ob_get_clean();

        $this->assertStringContainsString('id="ai_chat_button"', $output);
        // The system wide floating button instance is only created and used if the course block is not rendered.
        $systeminstances = $DB->count_records(
            'block_instances',
            ['blockname' => 'ai_chat', 'parentcontextid' => \context_system::instance()->id]
        );
        $this->assertSame($expectcourseblock ? 0 : 1, $systeminstances);
    }

    /**
     * Test the course settings form hooks.
     *
     * @return void
     * @throws \coding_exception
     *
     * @covers \hook_callbacks::handle_after_form_definition
     * @covers \hook_callbacks::handle_after_form_submission
     * @covers \hook_callbacks::handle_after_form_definition_after_data
     */
    public function test_course_settings_form_hooks(): void {
        global $CFG, $DB, $PAGE;
        require_once(__DIR__ . '/fixtures/mock_course_edit_form.php');

        $this->resetAfterTest();
        $this->setAdminUser();

        // Replace the version of the manager in the DI container with a phpunit one.
        \core\di::set(
            \core\hook\manager::class,
            \core\hook\manager::phpunit_get_instance([
                'block_ai_chat' => $CFG->dirroot . '/blocks/ai_chat/db/hooks.php',
            ]),
        );

        // We disable the restriction of tenants.

        $category = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $category->id]);
        $coursecontext = \context_course::instance($course->id);

        $PAGE->set_url(new \moodle_url('/course/edit.php', ['id' => $course->id]));

        $editoroptions = [
            'context' => $coursecontext,
        ];

        // Create a course settings edit form mock.
        $course = file_prepare_standard_editor(
            $course,
            'summary',
            $editoroptions,
            $coursecontext,
            'course',
            'summary',
            0
        );

        // Use a valid local return URL to satisfy PARAM_LOCALURL validation in course_edit_form.
        $validreturnurl = '/course/view.php?id=' . $course->id;

        // First of all define the case that the tenant is not restricted, but still not enabled.
        set_config('restricttenants', false, 'local_ai_manager');
        \core\di::get(\local_ai_manager\local\config_manager::class)->set_config('tenantenabled', false);
        $this->assertFalse(\core\di::get(\local_ai_manager\local\config_manager::class)->is_tenant_enabled());
        $editform = new mock_course_edit_form(null, [
            'course' => $course, 'category' => $category,
            'editoroptions' => $editoroptions, 'returnto' => '0', 'returnurl' => $validreturnurl,
        ]);

        // Now enable the tenant.
        \core\di::get(\local_ai_manager\local\config_manager::class)->set_config('tenantenabled', true);
        $this->assertTrue(\core\di::get(\local_ai_manager\local\config_manager::class)->is_tenant_enabled());
        $editform = new mock_course_edit_form(null, [
            'course' => $course, 'category' => $category,
            'editoroptions' => $editoroptions, 'returnto' => '0', 'returnurl' => $validreturnurl,
        ]);
        $mform = $editform->get_mform();
        $this->assertTrue($mform->elementExists('addaichat'));

        $addaichatelement = $mform->getElement('addaichat');
        $this->assertNotEmpty($addaichatelement);

        // Trigger definition_after_data.
        $editform->render();

        // There is no other instance of an ai chat block, so lement should be unchecked.
        $this->assertEquals(false, $addaichatelement->getValue());

        // Assert there is no general block instance existing.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertEmpty($aiblockinstances);

        // Update with addaichat flag set.
        $data = (object) $mform->exportValues();
        $data = file_postupdate_standard_editor(
            $data,
            'summary',
            $editoroptions,
            $coursecontext,
            'course',
            'summary',
            0
        );
        $data->addaichat = 1;
        // Update course triggers handle_after_form_submission.
        update_course($data, $editoroptions);

        // Assert there is one course block instance existing.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertCount(1, $aiblockinstances);
        $aiblockinstance = array_shift($aiblockinstances);

        $this->assertEquals($coursecontext->id, $aiblockinstance->parentcontextid);
        $this->assertEquals(1, $aiblockinstance->showinsubcontexts);
        $this->assertEquals('*', $aiblockinstance->pagetypepattern);

        // Assert that addaichat element is existing and is checked.
        $FULLME = $CFG->wwwroot . '/course/edit.php?id=' . $course->id;
        $editform = new mock_course_edit_form(null, [
            'course' => $course, 'category' => $category,
            'editoroptions' => $editoroptions, 'returnto' => '0', 'returnurl' => $validreturnurl,
        ]);
        $mform = $editform->get_mform();

        $addaichatelement = $mform->getElement('addaichat');
        $this->assertNotEmpty($addaichatelement);

        // Trigger definition_after_data.
        $editform->render();

        // There is no other instance of an ai chat block, so element should be checked.
        $this->assertEquals(true, $addaichatelement->getValue());

        // Update with addaichat flag set.
        $data = (object) $mform->exportValues();
        $data = file_postupdate_standard_editor($data, 'summary', $editoroptions, $coursecontext, 'course', 'summary', 0);
        $data->addaichat = 0;
        // Update course triggers handle_after_form_submission.
        update_course($data, $editoroptions);

        // Assert there is no course block instance existing.
        $aiblockinstances = $DB->get_records('block_instances', ['blockname' => 'ai_chat']);
        $this->assertCount(0, $aiblockinstances);
    }
}
