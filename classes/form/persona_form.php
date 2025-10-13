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

namespace block_ai_chat\form;

use core_form\dynamic_form;
use context;
use stdClass;
use function DI\get;

/**
 * Class base_form
 *
 * @package    block_ai_chat
 * @copyright  2025 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persona_form extends dynamic_form {
    /** @var array $personas */
    protected array $personas;

    /** @var int $blockcontextid */
    protected int $blockcontextid;

    /**
     * Form definition.
     */
    public function definition() {
        // Load default and user personas here since select options need to be inserted.
        [$userinfos, $this->personas, $names, $prompts, $templateids] = \block_ai_chat\local\persona::get_all_personas();

        // Stringify.
        $prompts = json_encode($prompts);
        $userinfos = json_encode($userinfos);
        $templateids = json_encode($templateids);

        $mform =& $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->optional_param('contextid', null, PARAM_INT));

        $mform->addElement('hidden', 'templateids');
        $mform->setType('templateids', PARAM_TEXT);
        $mform->setDefault('templateids', $templateids);

        $mform->addElement('hidden', 'prompts');
        $mform->setType('prompts', PARAM_TEXT);
        $mform->setDefault('prompts', $prompts);

        $mform->addElement('hidden', 'userinfos');
        $mform->setType('userinfos', PARAM_TEXT);
        $mform->setDefault('userinfos', $userinfos);

        $mform->addElement('hidden', 'delete');
        $mform->setType('delete', PARAM_INT);
        $mform->setDefault('delete', 0);

        $selectgroup = [];
        // Add a "+" icon after the select template input.
        $selectgroup[] = $mform->createElement('select', 'template', '', $names);
        $addicon = '<i id="add_persona" title ="' . get_string('addpersonatitle', 'block_ai_chat')
            . '" class="fa-regular fa-square-plus ml-2"></i>';
        $copyicon = '<i id="copy_persona" title ="' . get_string('copypersonatitle', 'block_ai_chat')
            . '" class="fa fa-copy ml-2"></i>';
        $selectgroup[] = $mform->createElement('html', $addicon . $copyicon);
        $mform->addGroup($selectgroup, 'selectgroup', get_string('template', 'block_ai_chat'), [' '], false);

        $mform->addElement('text', 'name', get_string('name', 'block_ai_chat'), ['class' => 'addname']);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('textarea', 'prompt', get_string('prompt', 'block_ai_chat'));
        $mform->setType('prompt', PARAM_TEXT);

        $mform->addElement('textarea', 'userinfo', get_string('userinfo', 'block_ai_chat'));
        $mform->setType('userinfo', PARAM_TEXT);

        $mform->addElement('hidden', 'systemtemplate', 0, ['data-type' => 'systemtemplate']);
    }

    /**
     * Returns the user context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        // When modal is built, contextid is passed as optional_param. For submission it is accessed via formdata.
        if (!isset($this->blockcontextid)) {
            $this->blockcontextid = $this->optional_param('contextid', null, PARAM_INT);
        }
        return \context::instance_by_id($this->blockcontextid);
    }

    /**
     *
     * Checks if current user has sufficient permissions, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        require_capability('block/ai_chat:addinstance', \context_system::instance());
    }

    /**
     * Form validation.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK (true allowed for backwards compatibility too).
     */
    public function validation($data, $files) {
        $errors = [];
        // Abort if delete button is pressed.
        if ($data['delete'] == "1") {
            return $errors;
        }
        // Option with $data['template'] = "0" is to delete the current persona selection.
        if (empty($data['name']) && $data['template'] != "0") {
            $errors['name'] = get_string('errorname', 'block_ai_chat');
        }
        if (empty($data['prompt']) && $data['template'] != "0") {
            $errors['prompt'] = get_string('errorprompt', 'block_ai_chat');
        }
        if (empty($data['userinfo']) && $data['template'] != "0") {
            $errors['userinfo'] = get_string('erroruserinfo', 'block_ai_chat');
        }
        return $errors;
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return array Returns whether a new source was created.
     */
    public function process_dynamic_submission(): array {
        global $USER, $DB;

        $formdata = $this->get_data();

        // Admintemplates are saved with userid 0, so change if admin is editing admintemplate or adds new template.
        if (
            is_siteadmin()
            && (
                $this->personas[$formdata->template]->userid == "0" // We edit a current system template.
                // New template that has been declared as system template.
                || (!isset($formdata->template) && intval($formdata->systemtemplate) === 1)
            )
        ) {
            $userid = "0";
        } else {
            $userid = $USER->id;
        }

        // Selection: No persona.
        if ($formdata->template == 0 && $formdata->name == "") {
            $params = ['contextid' => $formdata->contextid];
            $DB->delete_records('block_ai_chat_personas_selected', $params);
            return [
                'update' => true,
            ];
        }

        // Delete current persona.
        if ($formdata->delete == 1) {
            $params = ['id' => $formdata->template, 'userid' => $userid];
            $DB->delete_records('block_ai_chat_personas', $params);
            // Check if selected should be deleted too.
            $params = ['contextid' => $formdata->contextid, 'personasid' => $formdata->template];
            $personaselected = $DB->get_record_select(
                'block_ai_chat_personas_selected',
                'contextid = :contextid AND personasid = :personasid',
                $params
            );
            if ($personaselected) {
                $DB->delete_records('block_ai_chat_personas_selected', $params);
            }
            return [
                'update' => true,
            ];
        }

        // Save, so check if admintemplate, usertemplate or new template is chosen.
        // Is admintemplate and name, prompt and userinfo unchanged?
        if (
            $this->personas[$formdata->template]->userid == "0" &&
            $formdata->name == $this->personas[$formdata->template]->name &&
            $formdata->prompt == $this->personas[$formdata->template]->prompt &&
            $formdata->userinfo == $this->personas[$formdata->template]->userinfo
        ) {
            // Set selected.
            $personaselectednew = $formdata->template;
        } else if (isset($this->personas[$formdata->template])) {
            // Update if existing Persona exists.
            $record = new \stdClass();
            $record->id = $formdata->template;
            $record->userid = $userid;
            $record->name = $formdata->name;
            $record->prompt = $formdata->prompt;
            $record->userinfo = $formdata->userinfo;
            $record->timemodified = time();
            $DB->update_record('block_ai_chat_personas', $record);
            $personaselectednew = $formdata->template;
        } else {
            // If name is changed, create new entry.
            $record = new \stdClass();
            $record->userid = $userid;
            $record->name = $formdata->name;
            $record->prompt = $formdata->prompt;
            $record->userinfo = $formdata->userinfo;
            $record->timecreated = time();
            $record->timemodified = time();
            $personaselectednew = $DB->insert_record('block_ai_chat_personas', $record);
        }

        // Update personaselected.
        $personaselected = $DB->get_record_select('block_ai_chat_personas_selected', 'contextid = ?', [$formdata->contextid]);
        if ($personaselected) {
            // Update personas_selected.
            $record = new \stdClass();
            $record->id = $personaselected->id;
            $record->personasid = $personaselectednew;
            $DB->update_record('block_ai_chat_personas_selected', $record);
        } else {
            // Insert to personas_selected.
            $record = new \stdClass();
            $record->personasid = $personaselectednew;
            $record->contextid = $formdata->contextid;
            $DB->insert_record('block_ai_chat_personas_selected', $record);
        }
        return [
            'update' => true,
        ];
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        global $DB;

        $this->get_context_for_dynamic_submission();

        // Check if a persona is selected for this instance.
        $param = [$this->blockcontextid];
        $personaselected = $DB->get_record_select('block_ai_chat_personas_selected', 'contextid = ?', $param);
        if ($personaselected) {
            $data = [
                'template' => $personaselected->personasid,
                'name' => $this->personas[$personaselected->personasid]->name,
                'prompt' => $this->personas[$personaselected->personasid]->prompt,
                'userinfo' => $this->personas[$personaselected->personasid]->userinfo,
            ];
        } else {
            $data = [
                // No persona.
                'template' => 0,
            ];
        }

        $this->set_data($data);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX
     *
     * @return moodle_url
     */
    protected function get_page_url_for_dynamic_submission(): \moodle_url {
        return new \moodle_url('/block_ai_chat_persona_dummy.php');
    }
}
