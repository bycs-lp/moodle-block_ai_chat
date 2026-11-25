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

namespace block_ai_chat\local;

use context_block;
use context_system;
use local_ai_manager\ai_manager_utils;
use stdClass;

/**
 * Hook listener callbacks.
 *
 * @package    block_ai_chat
 * @copyright  2024 ISB Bayern
 * @author     Tobias Garske
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Add a checkbox to add an AI chat block.
     *
     * @param \core_course\hook\after_form_definition $hook
     */
    public static function handle_after_form_definition(\core_course\hook\after_form_definition $hook): void {
        $configmanager = \core\di::get(\local_ai_manager\local\config_manager::class);
        if ($configmanager->is_tenant_enabled()) {
            $mform = $hook->mform;
            ai_manager_utils::add_ai_tools_category_to_mform($mform);
            $mform->addElement(
                'checkbox',
                'addaichat',
                get_string('pluginname', 'block_ai_chat'),
                get_string('addblockinstance', 'block_ai_chat')
            );
            $mform->addHelpButton('addaichat', 'addblockinstance', 'block_ai_chat');
            $mform->setDefault('addaichat', 0);
        }
    }

    /**
     * Check for addaichat form setting and add/remove ai-chat block.
     *
     * @param \core_course\hook\after_form_submission $hook
     */
    public static function handle_after_form_submission(\core_course\hook\after_form_submission $hook): void {
        global $DB;
        // Get form data.
        $data = $hook->get_data();

        // Check if block_ai_chat instance is present.
        $courseid = $data->id;
        $blockinstance = helper::has_block_in_course_context($courseid);

        if (!empty($data->addaichat) && $data->addaichat == '1') {
            if (!$blockinstance) {
                // Add block instance.
                $newinstance = new \stdClass();
                $newinstance->blockname = 'ai_chat';
                $newinstance->parentcontextid = \context_course::instance($courseid)->id;
                // We want to make the block usable for single activity courses as well, so display in subcontexts.
                $newinstance->showinsubcontexts = 1;
                $newinstance->pagetypepattern = '*';
                $newinstance->subpagepattern = null;
                $newinstance->defaultregion = 'side-pre';
                $newinstance->defaultweight = 1;
                $newinstance->configdata = '';
                $newinstance->timecreated = time();
                $newinstance->timemodified = $newinstance->timecreated;
                $newinstance->id = $DB->insert_record('block_instances', $newinstance);
            }
        } else {
            // If tenant is not allowed, $data->addaichat will be empty,
            // so an existing instance will be deleted by following lines.
            if ($blockinstance) {
                // Remove block instance.
                blocks_delete_instance($blockinstance);
            }
        }
    }

    /**
     * Check if block instance is present and set addaichat form setting.
     *
     * @param \core_course\hook\after_form_definition_after_data $hook
     * @return void
     * @throws \dml_exception
     */
    public static function handle_after_form_definition_after_data(\core_course\hook\after_form_definition_after_data $hook): void {
        // Get form data.
        $mform = $hook->mform;
        $formwrapper = $hook->formwrapper;
        if (!empty($formwrapper->get_course()->id)) {
            $courseid = $formwrapper->get_course()->id;

            $blockinstance = helper::has_block_in_course_context($courseid);
            if ($blockinstance) {
                // Block present, so set checkbox accordingly.
                $mform->setDefault('addaichat', "checked");
            }
        }
    }

    /**
     * Insert a chatbot floating button on pagetypes which are defined in the related admin setting.
     *
     * @param \core\hook\output\before_footer_html_generation $hook the before footer html generation hook object
     */
    public static function handle_before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $DB, $PAGE;
        if (!helper::show_global_block($PAGE)) {
            return;
        }

        // Anchor the floating button to the course context the user is currently browsing, so that
        // role assignments (for example editing teachers) apply and both the capability check in
        // block_ai_chat::get_content() and the subsequent web service calls resolve against a context
        // where the user actually has rights. This makes the button work on course pages that use a
        // layout without block regions, such as course/editsection.php. Outside a course (dashboard,
        // admin pages, ...) we fall back to the system context, keeping the previous behaviour.
        $coursecontext = $PAGE->context->get_course_context(false);
        $parentcontext = $coursecontext ?: context_system::instance();

        $blockinstancerecord = $DB->get_record(
            'block_instances',
            [
                'blockname' => 'ai_chat',
                'parentcontextid' => $parentcontext->id,
                'pagetypepattern' => '',
                'showinsubcontexts' => 0,
            ]
        );

        if (!$blockinstancerecord) {
            $blockinstancerecord = self::create_floating_instance($PAGE, $parentcontext);
        }

        // The floating-button record is fully configured on creation (empty pagetypepattern so it is
        // never rendered as a regular block), so we deliberately do NOT call instance_create() here:
        // for a course-parented instance it would wrongly flip the pagetypepattern to '*'.
        if ($block = block_instance('ai_chat', $blockinstancerecord)) {
            echo $block->get_content()->text;
        }
    }

    /**
     * Create the hidden block instance used to render the floating chat button.
     *
     * The instance uses an empty pagetype pattern so it is never rendered as a regular block in a
     * region; it exists only so that {@see self::handle_before_footer_html_generation()} can render
     * the floating button through the block's get_content().
     *
     * @param \moodle_page $page the current page, used to determine a sensible default block region
     * @param \context $parentcontext the context the floating button instance is anchored to
     * @return stdClass the created block_instances record
     */
    private static function create_floating_instance(\moodle_page $page, \context $parentcontext): stdClass {
        global $DB;

        $blockinstancerecord = new stdClass();
        $blockinstancerecord->blockname = 'ai_chat';
        $blockinstancerecord->parentcontextid = $parentcontext->id;
        $blockinstancerecord->showinsubcontexts = false;
        $blockinstancerecord->requiredbytheme = false;
        $blockinstancerecord->pagetypepattern = '';
        $blockinstancerecord->subpagepattern = null;
        // Function get_default_region() returns null on layouts without block regions (e.g. the 'base' layout
        // used by course/editsection.php); fall back to 'side-pre' because defaultregion is NOT NULL.
        // The value is irrelevant anyway, as this instance is never rendered inside a theme region.
        $blockinstancerecord->defaultregion = $page->blocks->get_default_region() ?: 'side-pre';
        $blockinstancerecord->defaultweight = 0;
        $blockinstancerecord->configdata = '';
        $blockinstancerecord->timecreated = time();
        $blockinstancerecord->timemodified = $blockinstancerecord->timecreated;
        $blockinstancerecord->id = $DB->insert_record('block_instances', $blockinstancerecord);

        // Ensure the block context is created.
        context_block::instance($blockinstancerecord->id);

        return $blockinstancerecord;
    }

    /**
     * Add a bodyclass depending on the replacehelp setting.
     *
     * @param \core\hook\output\before_http_headers $hook the before html attributes generation hook object
     */
    public static function handle_before_http_headers(\core\hook\output\before_http_headers $hook): void {
        global $PAGE;

        if (get_config('block_ai_chat', 'replacehelp')) {
            $PAGE->add_body_class('block_ai_chat_replacehelp');
        }
    }

    /**
     * Provide additional information about which purposes are being used by this plugin.
     *
     * @param \local_ai_manager\hook\purpose_usage $hook the purpose_usage hook object
     */
    public static function handle_purpose_usage(\local_ai_manager\hook\purpose_usage $hook): void {
        $hook->set_component_displayname('block_ai_chat', get_string('pluginname_userfaced', 'block_ai_chat'));
        $hook->add_purpose_usage_description(
            'chat',
            'block_ai_chat',
            get_string('purposeplacedescription_mainwindow', 'block_ai_chat')
        );
        foreach (\tiny_ai\local\utils::get_purpose_placedescriptions() as $description) {
            $altereddescription = get_string('toolsofaibutton', 'block_ai_chat', $description['placedescription']);
            $hook->add_purpose_usage_description($description['purpose'], 'block_ai_chat', $altereddescription);
        }
    }
}
