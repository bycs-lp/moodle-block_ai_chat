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

/**
 * Class to retrieve MoodleQuickform from course_edit_form.
 *
 * @package   block_ai_chat
 * @copyright 2024 Andreas Wagner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mock_course_edit_form extends \course_edit_form {
    /**
     * Get the protected MoodleQuickForm.
     *
     * @return MoodleQuickForm the form used.
     */
    public function get_mform(): \MoodleQuickForm {
        return $this->_form;
    }
}
