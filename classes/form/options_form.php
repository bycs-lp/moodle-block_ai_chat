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
use function DI\get;

/**
 * Class base_form
 *
 * @package    block_ai_chat
 * @copyright  2025 Tobias Garske, ISB Bayern
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class options_form extends dynamic_form {
    /** @var int $blockcontextid */
    protected int $blockcontextid;

    /**
     * Form definition.
     */
    public function definition() {
        global $USER, $DB;

        // Load default and user personas here since select options need to be inserted.
        $contextid = $this->optional_param('contextid', null, PARAM_INT);

        $mform =& $this->_form;

        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);
        $mform->setDefault('contextid', $contextid);

        $mform->addElement('text', 'historycontextmax', get_string('historycontextmax', 'block_ai_chat'));
        $mform->setType('historycontextmax', PARAM_INT);
        $mform->setDefault('historycontextmax', 5);
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
        if (empty($data['historycontextmax']) || !is_int($data['historycontextmax'])) {
            $errors['name'] = get_string('errorhistorycontextmax', 'block_ai_chat');
        }
        return $errors;
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * @return array Returns whether a new source was created.
     */
    public function process_dynamic_submission(): array {
        global $DB;

        $formdata = $this->get_data();

        $context = $this->get_context_for_dynamic_submission();

        // Update historycontextmax.
        $historycontextmax = $DB->get_record_select(
            'block_ai_chat_options',
            'contextid = ? AND name = ?',
            [$formdata->contextid, 'historycontextmax']
        );
        if ($historycontextmax) {
            // Update config.
            $record = new \stdClass();
            $record->id = $historycontextmax->id;
            $record->value = $formdata->historycontextmax;
            $DB->update_record('block_ai_chat_options', $record);
        } else {
            // Insert to personas_selected.
            $record = new \stdClass();
            $record->name = 'historycontextmax';
            $record->value = $formdata->historycontextmax;
            $record->contextid = $formdata->contextid;
            $DB->insert_record('block_ai_chat_options', $record);
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

        // Get options for this instance.
        $param = [$this->blockcontextid];
        $options = $DB->get_records_select('block_ai_chat_options', 'contextid = ?', $param);
        // Set defaults.
        $data = [
            'historycontextmax' => 5,
        ];
        foreach ($options as $option) {
            $data[$option->name] = $option->value;
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
