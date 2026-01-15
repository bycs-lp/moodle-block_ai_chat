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
 * Edit page for additional AI contexts.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../config.php');
require_login();

global $CFG, $DB, $PAGE, $OUTPUT, $USER;

$systemcontext = context_system::instance();
$PAGE->set_context($systemcontext);
$PAGE->set_url('/blocks/ai_chat/edit_aicontext.php');
$PAGE->set_pagelayout('admin');

require_capability('block/ai_chat:manageaicontext', $systemcontext);

$id = optional_param('id', 0, PARAM_INT);
$del = optional_param('del', 0, PARAM_INT);

$returnurl = new moodle_url('/blocks/ai_chat/manage_aicontext.php');

if (!empty($del)) {
    if (empty($id)) {
        throw new moodle_exception('exception_aicontextidmissing', 'block_ai_chat');
    }
    require_sesskey();

    $aicontext = $DB->get_record('block_ai_chat_aicontext', ['id' => $id]);
    if (!$aicontext) {
        throw new moodle_exception('exception_aicontextnotfound', 'block_ai_chat', '', $id);
    }
    $aicontext = $DB->delete_records('block_ai_chat_aicontext', ['id' => $id]);

    redirect($returnurl, get_string('aicontextdeleted', 'block_ai_chat'));
}

$options = [];
if (!empty($id)) {
    $options['id'] = $id;
}

$actionurl = new moodle_url('/blocks/ai_chat/edit_aicontext.php', $options);

$aicontextform = new \block_ai_chat\form\edit_aicontext_form($actionurl, $options);
$aicontextformhandler = \core\di::get(\block_ai_chat\local\aicontext_form_handler::class);

// Standard form processing if statement.
if ($aicontextform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $aicontextform->get_data()) {
    // TODO extract to a utility class/function.
    $aicontextformhandler->store_form_data($data);
    redirect($returnurl, get_string('aicontextsaved', 'block_ai_chat'));
} else {
    if (!empty($id)) {
        $PAGE->set_url('/blocks/ai_chat/edit_aicontext.php', ['id' => $id]);
        $aicontextform->set_data($aicontextformhandler->get_data_for_aicontext_form($id));
    }
    echo $OUTPUT->header();
    echo html_writer::start_div('w-75 d-flex flex-column align-items-center ml-auto mr-auto');
    echo $OUTPUT->render_from_template(
        'block_ai_chat/edit_aicontext_heading',
        [
            'heading' => $OUTPUT->heading(get_string('manageaicontext', 'block_ai_chat')),
            'showdeletebutton' => !empty($id),
            'deleteurl' => new moodle_url(
                '/blocks/ai_chat/edit_aicontext.php',
                ['id' => $id, 'del' => 1, 'sesskey' => sesskey()]
            ),
        ]
    );

    echo html_writer::start_div('w-75');
    $aicontextform->display();
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
