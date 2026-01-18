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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/ai_chat/backup/moodle2/backup_ai_chat_block_stepslib.php');

/**
 * Backup class for block_ai_chat
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ai_chat_block_task extends backup_block_task {
    /**
     * Does nothing.
     *
     * @return void
     */
    protected function define_my_settings() {
    }

    /**
     * Add step.
     *
     * @return void
     */
    protected function define_my_steps() {
        $this->add_step(new backup_ai_chat_block_structure_step('aichat', 'aichat.xml'));
    }

    /**
     * This plugin has no fileareas yet.
     *
     * @return array
     */
    public function get_fileareas() {
        return [];
    }

    /**
     * No encoded attributes in configdata yet.
     *
     * @return array
     */
    public function get_configdata_encoded_attributes() {
        return [];
    }

    /**
     * Returns the unchanged parameter.
     *
     * @param string $content
     * @return string
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
