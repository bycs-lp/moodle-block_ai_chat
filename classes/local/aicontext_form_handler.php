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

use stdClass;

/**
 * Class aicontext_form_handler.
 *
 * @package   block_ai_chat
 * @copyright 2025 ISB Bayern
 * @author    Philipp Memmel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aicontext_form_handler {
    /**
     * Retrieve data from database to inject into the aicontext form.
     *
     * @param int $aicontextid the id of the aicontext record.
     * @return stdClass the data to inject into the form.
     */
    public function get_data_for_aicontext_form(int $aicontextid): stdClass {
        global $DB;
        $aicontextrecord = $DB->get_record('block_ai_chat_aicontext', ['id' => $aicontextid]);
        $data = new stdClass();
        $data->name = $aicontextrecord->name;
        $data->description = $aicontextrecord->description;
        $data->content = $aicontextrecord->content;
        $data->enabled = $aicontextrecord->enabled;
        $pagetypes = $DB->get_fieldset('block_ai_chat_aicontext_usage', 'pagetype', ['aicontextid' => $aicontextid]);
        $data->pagetypes = implode(PHP_EOL, $pagetypes);
        return $data;
    }

    /**
     * Store the data from the aicontext form into the database.
     *
     * @param stdClass $data the data from the form.
     */
    public function store_form_data(stdClass $data): void {
        global $DB;
        $aicontextrecord = new stdClass();
        if (isset($data->id)) {
            $aicontextrecord->id = $data->id;
        }
        $aicontextrecord->name = trim($data->name);
        // Description is optional, thus could be null.
        $aicontextrecord->description = property_exists($data, 'description') ? trim($data->description) : null;
        $aicontextrecord->content = trim($data->content);
        $aicontextrecord->enabled = $data->enabled ? 1 : 0;
        $aicontextrecord->timemodified = time();
        if (!empty($aicontextrecord->id)) {
            $DB->update_record('block_ai_chat_aicontext', $aicontextrecord);
        } else {
            $aicontextrecord->timecreated = $aicontextrecord->timemodified;
            $aicontextrecord->id = $DB->insert_record('block_ai_chat_aicontext', $aicontextrecord);
        }

        $pagetypes = explode(PHP_EOL, $data->pagetypes);
        $pagetypestostore = [];
        foreach ($pagetypes as $pagetype) {
            $pagetype = trim($pagetype);
            if (!empty($pagetype)) {
                $pagetypestostore[] = $pagetype;
            }
        }

        $existingpagetypeassignmententries = isset($data->id) ?
            $DB->get_records('block_ai_chat_aicontext_usage', ['aicontextid' => $aicontextrecord->id]) : [];
        $existingpagetypes = array_map(fn($record) => $record->pagetype, $existingpagetypeassignmententries);
        foreach ($pagetypestostore as $pagetype) {
            if (!in_array($pagetype, $existingpagetypes)) {
                $DB->insert_record(
                    'block_ai_chat_aicontext_usage',
                    [
                        'pagetype' => $pagetype,
                        'aicontextid' => $aicontextrecord->id,
                        'timecreated' => time(),
                    ]
                );
            }
        }
        $pagetypestodelete = array_diff($existingpagetypes, $pagetypestostore);
        // If there still are entries left, they need to be deleted.
        if (!empty($pagetypestodelete)) {
            [$insql, $inparams] = $DB->get_in_or_equal($pagetypestodelete, SQL_PARAMS_NAMED);
            $idstodelete = $DB->get_fieldset_select(
                'block_ai_chat_aicontext_usage',
                'id',
                "pagetype $insql AND aicontextid = :aicontextid",
                array_merge($inparams, ['aicontextid' => $aicontextrecord->id])
            );
            $DB->delete_records_list('block_ai_chat_aicontext_usage', 'id', $idstodelete);
        }
    }
}
