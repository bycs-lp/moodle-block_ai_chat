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
 * Backup steps for block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2026 ISB Bayern
 * @author     Stefan Hanauska <stefan.hanauska@csg-in.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_ai_chat_block_structure_step extends backup_block_structure_step {
    /**
     * Define structure.
     */
    protected function define_structure() {
        $userinfo = $this->get_setting_value('users');

        // Wrapper without any attributes.
        $aichat = new backup_nested_element('aichat', ['id']);

        $personas = new backup_nested_element('personas');
        $personacolumns = ['name', 'prompt', 'userinfo', 'type', 'timecreated', 'timemodified'];
        if ($userinfo) {
            $personacolumns[] = 'userid';
        }
        $persona = new backup_nested_element('persona', ['id'], $personacolumns);

        $personasselected = new backup_nested_element('personas_selected');
        $personaselected = new backup_nested_element('persona_selected', ['id'], ['personasid', 'contextid']);

        $chatoptions = new backup_nested_element('chat_options');
        $chatoption = new backup_nested_element('chat_option', ['id'], ['name', 'value', 'contextid']);

        // Prepare the structure.
        $wrapper = $this->prepare_block_structure($aichat);

        $aichat->add_child($personas);
        $personas->add_child($persona);

        $aichat->add_child($personasselected);
        $personasselected->add_child($personaselected);

        $aichat->add_child($chatoptions);
        $chatoptions->add_child($chatoption);

        // Define sources.
        $aichat->set_source_array(['id' => $this->task->get_blockid()]);
        $persona->set_source_sql(
            '
            SELECT p.id, p.' . implode(', p.', $personacolumns) . '
              FROM {block_ai_chat_personas} p, {block_ai_chat_personas_selected} ps
             WHERE p.id = ps.personasid
               AND ps.contextid = ?',
            [backup::VAR_CONTEXTID]
        );
        $personaselected->set_source_table('block_ai_chat_personas_selected', ['contextid' => backup::VAR_CONTEXTID]);
        $chatoption->set_source_table('block_ai_chat_options', ['contextid' => backup::VAR_CONTEXTID]);

        return $wrapper;
    }
}
