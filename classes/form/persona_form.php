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

use block_ai_chat\local\persona;
use block_ai_chat\manager;
use core_form\dynamic_form;
use context;
use moodle_url;
use stdClass;

/**
 * Dynamic form class for editing a persona.
 *
 * @package    block_ai_chat
 * @copyright  2025 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class persona_form extends dynamic_form {
    /** @var int $contextid */
    protected int $contextid;

    /**
     * Form definition.
     */
    public function definition() {
        global $OUTPUT;
        $mform =& $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $this->optional_param('contextid', null, PARAM_INT));

        $mform->addElement('hidden', 'personaid');
        $mform->setType('personaid', PARAM_INT);

        $mform->addElement('hidden', 'userid');
        $mform->setType('userinfo', PARAM_INT);

        if (intval($this->_ajaxformdata['type']) === persona::TYPE_TEMPLATE) {
            $warninghtml = $OUTPUT->render_from_template('block_ai_chat/templateedit_warning', []);
            $mform->addElement('html', $warninghtml);
        }

        $mform->addElement('text', 'name', get_string('name', 'block_ai_chat'));
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('textarea', 'prompt', get_string('prompt', 'block_ai_chat'));
        $mform->setType('prompt', PARAM_RAW);

        $mform->addElement('textarea', 'userinfo', get_string('userinfo', 'block_ai_chat'));
        $mform->setType('userinfo', PARAM_RAW);

        if (has_capability('block/ai_chat:managepersonatemplates', $this->get_context_for_dynamic_submission())) {
            $mform->addElement(
                'select',
                'type',
                get_string('type', 'block_ai_chat'),
                [
                    persona::TYPE_USER => get_string('typeuser', 'block_ai_chat'),
                    persona::TYPE_TEMPLATE => get_string('typetemplate', 'block_ai_chat'),
                ]
            );
        } else {
            $mform->addElement('hidden', 'type');
            $mform->setType('type', PARAM_INT);
            $mform->setDefault('type', persona::TYPE_USER);
        }
    }

    /**
     * Returns the user context
     *
     * @return context
     */
    protected function get_context_for_dynamic_submission(): context {
        // When modal is built, contextid is passed as optional_param. For submission it is accessed via formdata.
        if (!isset($this->contextid)) {
            $this->contextid = $this->optional_param('contextid', null, PARAM_INT);
        }
        return \context::instance_by_id($this->contextid);
    }

    /**
     *
     * Checks if current user has sufficient permissions, otherwise throws exception
     */
    protected function check_access_for_dynamic_submission(): void {
        global $USER;
        require_capability('block/ai_chat:view', $this->get_context_for_dynamic_submission());
        $manager = new manager($this->contextid);
        $manager->require_manage_persona($this->_ajaxformdata['personaid'], $USER->id);
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
        if (empty($data['name'])) {
            $errors['name'] = get_string('errorname', 'block_ai_chat');
        }
        if (empty($data['prompt'])) {
            $errors['prompt'] = get_string('errorprompt', 'block_ai_chat');
        }
        if (empty($data['userinfo'])) {
            $errors['userinfo'] = get_string('erroruserinfo', 'block_ai_chat');
        }
        if (
            intval($data['type']) === persona::TYPE_TEMPLATE &&
            !has_capability('block/ai_chat:managepersonatemplates', $this->get_context_for_dynamic_submission())
        ) {
            $errors['type'] = get_string('errornotallowedtochangetype', 'block_ai_chat');
        }
        return $errors;
    }

    /**
     * Process the form submission, used if form was submitted via AJAX.
     *
     * @return array Returns the reactive state updates caused by this form.
     */
    public function process_dynamic_submission(): array {
        global $USER;

        $formdata = $this->get_data();

        $personadata = new stdClass();
        $personadata->id = $formdata->personaid;
        $personadata->userid = $formdata->userid;
        $personadata->name = $formdata->name;
        // The attributes prompt and userinfo are raw params, need to be sanitized on output.
        $personadata->prompt = $formdata->prompt;
        $personadata->userinfo = $formdata->userinfo;
        $personadata->type = isset($formdata->type) ? $formdata->type : null;

        $manager = new manager($this->contextid);
        return $manager->edit_persona($personadata, $USER->id);
    }

    /**
     * Load in existing data as form defaults
     */
    public function set_data_for_dynamic_submission(): void {
        $this->get_context_for_dynamic_submission();

        $data = [
            'personaid' => $this->_ajaxformdata['personaid'],
            'userid' => $this->_ajaxformdata['userid'],
            'name' => $this->_ajaxformdata['name'],
            'prompt' => clean_text($this->_ajaxformdata['prompt']),
            'userinfo' => clean_text($this->_ajaxformdata['userinfo']),
            'type' => $this->_ajaxformdata['type'],
        ];

        $this->set_data($data);
    }

    /**
     * Returns url to set in $PAGE->set_url() when form is being rendered or submitted via AJAX.
     *
     * @return moodle_url a dummy url, because we do not really need this case
     */
    protected function get_page_url_for_dynamic_submission(): moodle_url {
        return new moodle_url('/block_ai_chat_persona_dummy.php');
    }
}
