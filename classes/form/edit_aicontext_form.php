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

defined('MOODLE_INTERNAL') || die;

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form for editing an AI context.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class edit_aicontext_form extends \moodleform {
    #[\Override]
    public function definition() {
        $mform = &$this->_form;
        if (!empty($this->_customdata['id'])) {
            $mform->addElement('hidden', 'id', $this->_customdata['id']);
            $mform->setType('id', PARAM_INT);
        }

        $textelementparams = ['style' => 'width: 100%'];
        $textareaparams = ['rows' => 10, 'style' => 'width: 100%'];

        $mform->addElement('text', 'name', get_string('name', 'block_ai_chat'), $textelementparams);
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('textarea', 'description', get_string('aicontextdescription', 'block_ai_chat'), $textareaparams);
        $mform->setType('description', PARAM_TEXT);

        $mform->addElement('textarea', 'content', get_string('aicontext', 'block_ai_chat'), $textareaparams);
        $mform->setType('content', PARAM_TEXT);

        $mform->addElement('textarea', 'pagetypes', get_string('pagetypes', 'block_ai_chat'), $textareaparams);
        $mform->setType('pagetypes', PARAM_TEXT);

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'block_ai_chat'));
        $mform->setType('enabled', PARAM_TEXT);
        $mform->setDefault('enabled', 1);

        $this->add_action_buttons();
    }

    #[\Override]
    public function validation($data, $files): array {
        $errors = [];
        if (empty(trim($data['name']))) {
            $errors['name'] = get_string('errorformfieldempty', 'block_ai_chat');
        }
        if (empty(trim($data['content']))) {
            $errors['content'] = get_string('errorformfieldempty', 'block_ai_chat');
        }
        // TODO Validate pageids: check if they are not double and have the correct form.
        return $errors;
    }
}
