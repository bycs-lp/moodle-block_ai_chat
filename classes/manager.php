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

use block_ai_chat\local\options;
use block_ai_chat\local\persona;
use context;
use cm_info;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use local_ai_manager\ai_manager_utils;
use stdClass;
use core_external\external_value;
use moodle_exception;

/**
 * Manager class handling backend state mutations for the reactive state of block_ai_chat.
 *
 * @package    block_ai_chat
 * @copyright  2025 ISB Bayern
 * @author     Philipp Memmel
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
    /** @var context the current context */
    private context $context;

    /** @var string the component name of the plugin using the AI chat */
    private string $component;

    /**
     * Class constructor.
     *
     * @param int $contextid The context id of the AI chat instance
     * @param string $component The component name of the plugin using the AI chat
     */
    public function __construct(int $contextid, string $component) {
        $this->context = \context_helper::instance_by_id($contextid);
        $this->component = $component;
    }

    /**
     * Mark entries of a conversation as deleted.
     *
     * @param int $userid the conversation of the user id
     * @param int $conversationid the id of the conversation
     * @return array reactive UI state updates
     */
    public function delete_conversation(int $userid, int $conversationid): array {
        $deletedids = \local_ai_manager\ai_manager_utils::mark_log_entries_as_deleted(
            $this->component,
            $this->context->id,
            $userid,
            $conversationid
        );

        $returnarray = [];
        foreach ($deletedids as $deletedid) {
            $returnarray[] = [
                'name' => 'messages',
                'action' => 'delete',
                'fields' => json_encode(['id' => $deletedid . '-1']),
            ];
            $returnarray[] = [
                'name' => 'messages',
                'action' => 'delete',
                'fields' => json_encode(['id' => $deletedid . '-2']),
            ];
        }
        return [
            'code' => 200,
            'content' => $returnarray,
        ];
    }

    /**
     * Getter for the context.
     *
     * @return context
     */
    public function get_context(): context {
        return $this->context;
    }

    /**
     * Create a dummy persona.
     */
    public function create_dummy_persona(): array {
        global $USER, $DB;
        $clock = \core\di::get(\core\clock::class);
        $time = $clock->time();
        $personaobject = (object) [
            'userid' => $USER->id,
            'name' => get_string('newpersonadefaultname', 'block_ai_chat'),
            'prompt' => get_string('newpersonadefaultprompt', 'block_ai_chat'),
            'userinfo' => get_string('newpersonadefaultuserinfo', 'block_ai_chat'),
            'type' => persona::TYPE_USER,
            'timemodified' => $time,
            'timecreated' => $time,
        ];

        $personaobject->id = $DB->insert_record('block_ai_chat_personas', $personaobject);

        $returnpersona = [
            'id' => $personaobject->id,
            'userid' => $personaobject->userid,
            'name' => $personaobject->name,
            'prompt' => $personaobject->prompt,
            'userinfo' => $personaobject->userinfo,
            'type' => $personaobject->type,
        ];

        return [
            'code' => 200,
            'content' => [
                [
                    'name' => 'personas',
                    'action' => 'put',
                    'fields' => json_encode($returnpersona),
                ],
            ],
        ];
    }

    /**
     * Delete a persona.
     *
     * Does not contain access checks, must be done before calling this method.
     *
     * @param int $personaid the id of the persona
     * @return array reactive UI state updates
     */
    public function delete_persona(int $personaid): array {
        global $DB;

        // We need to first remove all references to this persona across all chat bot instances.
        $personacurrentlyselected =
            $DB->record_exists('block_ai_chat_personas_selected', ['contextid' => $this->context->id, 'personasid' => $personaid]);
        $DB->delete_records('block_ai_chat_personas_selected', ['personasid' => $personaid]);

        $DB->delete_records('block_ai_chat_personas', ['id' => $personaid]);

        $returnarray = [
            [
                'name' => 'personas',
                'action' => 'delete',
                'fields' => json_encode(['id' => $personaid]),
            ],
        ];
        if ($personacurrentlyselected) {
            $returnarray[] = [
                'name' => 'config',
                'action' => 'update',
                'fields' => json_encode([
                    'currentPersona' => 0,
                    'currentlyMarkedPersona' => 0,
                ]),
            ];
        }
        return [
            'code' => 200,
            'content' => $returnarray,
        ];
    }

    /**
     * Edit a persona.
     *
     * Contains also access checks.
     *
     * @param stdClass $data the data object containing the persona fields
     * @param int $userid the id of the user performing the edit
     */
    public function edit_persona(stdClass $data, int $userid = 0): array {
        global $DB;
        if (intval($data->type) === persona::TYPE_TEMPLATE) {
            require_capability('block/ai_chat:managepersonatemplates', $this->context, $userid);
        } else {
            require_capability('block/ai_chat:view', $this->context, $userid);
        }

        $currentrecord = $DB->get_record('block_ai_chat_personas', ['id' => $data->id]);
        if (!$currentrecord) {
            throw new moodle_exception('errorpersonanotfound', 'block_ai_chat');
        }

        $personaobject = (object) [
            'id' => $data->id,
            'userid' => $data->userid,
            'name' => $data->name,
            'prompt' => $data->prompt,
            'userinfo' => html_to_text($data->userinfo),
            'type' => is_null($data->type) ? $currentrecord->type : $data->type,
            'timemodified' => time(),
        ];

        $DB->update_record('block_ai_chat_personas', $personaobject);

        $returnpersona = [
            'id' => $personaobject->id,
            'userid' => $personaobject->userid,
            'name' => $personaobject->name,
            'prompt' => $personaobject->prompt,
            'userinfo' => $personaobject->userinfo,
            'type' => $personaobject->type,
        ];

        return [
            'code' => 200,
            'content' => [
                [
                    'name' => 'personas',
                    'action' => 'update',
                    'fields' => json_encode($returnpersona),
                ],
            ],
        ];
    }

    /**
     * Returns all available personas.
     *
     * CARE: This will return all personas available to the user PLUS the one persona
     * which is currently selected for this block instance, if there is one selected.
     *
     * This function will also sanitize the output.
     *
     * @param int $userid the id of the user for which personas should be retrieved
     * @return array list of persona object
     */
    public function get_personas(int $userid = 0): array {
        global $DB, $USER;
        if ($userid === 0) {
            $userid = $USER->id;
        }
        $personasforuser = persona::get_all_personas($userid);
        $sql = "SELECT p.*
                FROM {block_ai_chat_personas} p
                JOIN {block_ai_chat_personas_selected} s ON s.personasid = p.id
                WHERE s.contextid = :contextid";
        $params = [
            'contextid' => $this->context->id,
        ];
        $personaofcurrentchat = $DB->get_record_sql($sql, $params);
        $personas = $personaofcurrentchat ? array_merge($personasforuser, [$personaofcurrentchat]) : $personasforuser;
        foreach ($personas as $persona) {
            $persona->userinfo = format_text($persona->userinfo, FORMAT_MOODLE, ['para' => false]);
            $persona->prompt = format_text($persona->prompt, FORMAT_MOODLE, ['para' => false]);
        }
        return !empty($personas) ? $personas : [];
    }

    /**
     * Get all messages for a given conversation.
     *
     * @param int $userid the id of the user
     * @param int $conversationid the id of the conversation
     * @return array response with code and content as reactive UI state updates
     */
    public function get_messages(int $userid, int $conversationid): array {
        // We limit to purpose 'chat' here because we do not want the requests from the integrated tiny_ai tools to be loaded
        // for displaying our conversations. This especially is a performance issue, because the field 'requestoptions' contains
        // base64 decoded images for purpose 'itt', for example, which slows down the database query extremely.
        $logentries = \local_ai_manager\ai_manager_utils::get_log_entries(
            $this->component,
            $this->context->id,
            $userid,
            $conversationid,
            false,
            '*',
            ['chat', 'agent']
        );
        // Tool-agent turns are stored in local_ai_manager_agent_runs, not in request_log.
        // Merge them in so the conversation history shows tool-agent interactions after page reload.
        $agentruns = \local_ai_manager\local\agent\entity\agent_run::get_records(
            [
                'conversationid' => $conversationid,
                'userid' => $userid,
                'component' => $this->component,
                'contextid' => $this->context->id,
                'status' => \local_ai_manager\local\agent\entity\agent_run::STATUS_COMPLETED,
            ],
            'started',
            'ASC',
        );

        // Build a combined timeline ordered by creation time so interleaved chat + tool-agent messages stay in order.
        $timeline = [];
        foreach ($logentries as $logentry) {
            $timeline[] = [
                'sortkey' => (int) $logentry->timecreated,
                'type' => 'log',
                'entry' => $logentry,
            ];
        }
        foreach ($agentruns as $run) {
            $finaltext = (string) $run->get('final_text');
            if ($finaltext === '') {
                continue;
            }
            $timeline[] = [
                'sortkey' => (int) $run->get('started'),
                'type' => 'run',
                'entry' => $run,
            ];
        }
        usort($timeline, static fn($a, $b) => $a['sortkey'] <=> $b['sortkey']);

        $messages = [];
        foreach ($timeline as $item) {
            if ($item['type'] === 'log') {
                $messages = array_merge($messages, $this->convert_log_entry_to_messages($item['entry']));
            } else {
                $messages = array_merge($messages, $this->convert_agent_run_to_messages($item['entry']));
            }
        }

        // MBS-10761: After a page reload, re-hydrate any tool-agent run that is still awaiting
        // approval so the approval card reappears in the UI.
        $pendingstate = $this->build_pending_agent_state($userid, $conversationid);
        if ($pendingstate !== null) {
            $messages[] = $pendingstate;
        }
        return [
            'code' => 200,
            'content' => $messages,
        ];
    }

    /**
     * Helper function to get the latest conversation id for a user in this context.
     *
     * @param int $userid the id of the user
     * @return int the latest conversation id, or 0 if no conversation exists
     */
    public function get_latest_conversationid(int $userid): int {
        $logentries = ai_manager_utils::get_log_entries(
            $this->component,
            $this->context->id,
            $userid,
            0,
            false,
            'itemid',
            ['chat', 'agent'],
            1
        );
        $latest = 0;
        $latesttime = 0;
        if (!empty($logentries)) {
            $entry = array_values($logentries)[0];
            if (!empty($entry->itemid)) {
                $latest = (int) $entry->itemid;
                $latesttime = (int) ($entry->timecreated ?? 0);
            }
        }
        // Also consider conversations that only contain tool-agent turns.
        $agentruns = \local_ai_manager\local\agent\entity\agent_run::get_records(
            [
                'userid' => $userid,
                'component' => $this->component,
                'contextid' => $this->context->id,
            ],
            'started',
            'DESC',
        );
        foreach ($agentruns as $run) {
            $conversationid = (int) $run->get('conversationid');
            if ($conversationid <= 0) {
                continue;
            }
            $started = (int) $run->get('started');
            if ($started > $latesttime) {
                $latest = $conversationid;
                $latesttime = $started;
            }
            break;
        }
        return $latest;
    }

    /**
     * Select a persona for this ai_chat instance.
     *
     * @param int $personaid The ID of the persona to select, or 0 to deselect any persona.
     * @return array reactive UI state update
     */
    public function select_persona(int $personaid): array {
        global $DB;
        if ($personaid === 0) {
            $DB->delete_records('block_ai_chat_personas_selected', ['contextid' => $this->context->id]);
        }
        $currentrecord = $DB->get_record('block_ai_chat_personas_selected', ['contextid' => $this->context->id]);
        if ($currentrecord) {
            $currentrecord->personasid = $personaid;
            $DB->update_record('block_ai_chat_personas_selected', $currentrecord);
        } else {
            $newrecord = new \stdClass();
            $newrecord->contextid = $this->context->id;
            $newrecord->personasid = $personaid;
            $DB->insert_record('block_ai_chat_personas_selected', $newrecord);
        }

        return [
            'code' => 200,
            'content' => [
                [
                    'name' => 'config',
                    'action' => 'update',
                    'fields' => json_encode([
                        'currentPersona' => $personaid,
                    ]),
                ],
            ],
        ];
    }

    /**
     * Defines the general structure of a block_ai_chat external function returning state updates for the reactive UI.
     *
     * The fields attribute inside the state update is encoded as JSON strings so we can use this structure
     * for different kind of state updates in the same external function response.
     *
     * @return external_single_structure the update structure
     */
    public static function get_update_structure(): external_single_structure {
        return
            new external_single_structure(
                [
                    'code' => new external_value(PARAM_INT, 'The response code'),
                    'message' => new external_value(PARAM_TEXT, 'The response message', VALUE_DEFAULT, ''),
                    'debuginfo' => new external_value(PARAM_TEXT, 'Debug information', VALUE_DEFAULT, ''),
                    'content' =>
                        new external_multiple_structure(
                            new external_single_structure(
                                [
                                    'name' => new external_value(PARAM_TEXT, 'The state element to update'),
                                    'action' => new external_value(PARAM_TEXT, 'The action to perform'),
                                    'fields' => new external_value(PARAM_RAW, 'JSON object with updated/new/deleted fields'),
                                ]
                            ),
                            'Update structure for returning a state update'
                        ),
                ]
            );
    }

    /**
     * Perform an AI request and return the resulting messages in reactive UI state update format.
     *
     * @param string $prompt the prompt that should be sent to the AI
     * @param string $mode the mode to be used (can be "chat" or "agent")
     * @param array $options additional request options
     * @return array response with code, message, debuginfo and content as reactive UI state updates
     */
    public function request_ai(string $prompt, string $mode, array $options): array {
        global $DB, $USER;
        if (empty($options['conversationid'])) {
            $conversationid = ai_manager_utils::get_next_free_itemid('block_ai_chat', $this->context->id);
        } else {
            $conversationid = $options['conversationid'];
        }
        $options['itemid'] = $conversationid;
        unset($options['conversationid']);

        // MBS-10761: Normalise the mode name so both legacy (`agent`) and canonical (`formassist`) work.
        $mode = self::normalise_mode($mode);

        // MBS-10761: Capability dispatch per mode and per-conversation mode lock.
        $this->require_mode_capability($mode);
        $lockerror = $this->ensure_conversation_mode((int) $options['itemid'], $USER->id, $mode);
        if ($lockerror !== null) {
            return $lockerror;
        }

        // MBS-10761: Tool-agent mode dispatches to the orchestrator instead of a purpose-based request.
        if ($mode === 'toolagent') {
            return $this->request_toolagent($prompt, (int) $options['itemid'], $options);
        }

        // MBS-10761: Canonical `formassist` maps to the `agent` purpose in local_ai_manager.
        $purpose = $mode === 'formassist' ? 'agent' : $mode;
        $optionsrecords = options::get_options($this->context->id);
        $conversationlimit = 5;
        if ($optionsrecords && array_filter($optionsrecords, fn($record) => $record->name === 'historycontextmax') > 0) {
            $historycontextmaxrecord =
                array_values(array_filter($optionsrecords, fn($record) => $record->name === 'historycontextmax'))[0];
            $conversationlimit = (int) $historycontextmaxrecord->value;
        }
        $options['conversationcontext'] = $this->retrieve_conversationcontext($options['itemid'], $USER->id, $conversationlimit);
        if ($mode === 'chat') {
            // Persona only makes sense in chat mode.
            $currentpersona = persona::get_current_persona($this->context->id);
            if (!empty($currentpersona)) {
                $options['conversationcontext'] = array_merge(
                    [
                        [
                            'sender' => 'system',
                            'message' => $currentpersona->prompt,
                        ],
                    ],
                    $options['conversationcontext']
                );
            }
        }

        if (!empty($options['agentoptions']['pageid'])) {
            [$pagetypeinsql, $pagetypeinparams] =
                $DB->get_in_or_equal(
                    matching_page_type_patterns($options['agentoptions']['pageid']),
                    SQL_PARAMS_NAMED
                );
            $sql = "SELECT aic.content FROM {block_ai_chat_aicontext_usage} u
                JOIN {block_ai_chat_aicontext} aic ON u.aicontextid = aic.id
               WHERE u.pagetype $pagetypeinsql AND aic.enabled = :enabled";
            $additionalcontexts = $DB->get_fieldset_sql(
                $sql,
                [
                    ...$pagetypeinparams,
                    'enabled' => 1,
                ]
            );
            $options['agentoptions']['additionalcontext'] =
                trim(array_reduce($additionalcontexts, fn($carry, $item) => $carry . PHP_EOL . trim($item), ''));
        }

        $aimanager = new \local_ai_manager\manager($purpose);
        $requestresult = $aimanager->perform_request($prompt, $this->component, $this->context->id, $options);
        if ($requestresult->get_code() !== 200) {
            return [
                'code' => $requestresult->get_code(),
                'message' => $requestresult->get_errormessage(),
                'debuginfo' => $requestresult->get_debuginfo(),
                'content' => [],
            ];
        }
        $logentry = $DB->get_record('local_ai_manager_request_log', ['id' => $requestresult->get_logrecordid()]);

        return ['code' => 200, 'content' => $this->convert_log_entry_to_messages($logentry)];
    }

    /**
     * Dispatch a tool-agent request to the {@see \local_ai_manager\agent\orchestrator} (MBS-10761).
     *
     * Returns reactive UI state updates containing the user prompt, the assistant answer
     * (when the run is complete), and — when the orchestrator paused for approval — the
     * agent state (pending approvals, run id, status).
     *
     * @param string $prompt User prompt.
     * @param int $conversationid Conversation / item id.
     * @param array $options Raw options array; `runid` may be set to resume an existing run,
     *                       `draftitemids` may list draft file areas.
     * @return array Reactive UI payload with `code`, `message`, `content`.
     */
    protected function request_toolagent(string $prompt, int $conversationid, array $options): array {
        global $USER;
        try {
            /** @var \local_ai_manager\external\agent_runner_factory $factory */
            $factory = \core\di::get(\local_ai_manager\external\agent_runner_factory::class);
            $orchestrator = $factory->build($this->component, $this->context);
        } catch (\Throwable $e) {
            return [
                'code' => 503,
                'message' => get_string('agenttoolagent_disabled', 'block_ai_chat'),
                'debuginfo' => $e->getMessage(),
                'content' => [],
            ];
        }

        try {
            $resumeid = (int) ($options['runid'] ?? 0);
            $draftitemids = array_values(array_map('intval', (array) ($options['draftitemids'] ?? [])));
            if ($resumeid > 0) {
                $result = $orchestrator->resume($resumeid, $USER, $this->context, $draftitemids);
            } else {
                $result = $orchestrator->run(
                    $USER,
                    $this->context,
                    $prompt,
                    $conversationid,
                    null,
                    $this->component,
                    $draftitemids,
                );
            }
        } catch (\moodle_exception $e) {
            return [
                'code' => 500,
                'message' => $e->getMessage(),
                'debuginfo' => '',
                'content' => [],
            ];
        }

        return [
            'code' => 200,
            'content' => $this->convert_run_result_to_updates($result, $prompt, $conversationid),
        ];
    }

    /**
     * Turn a {@see \local_ai_manager\agent\run_result} into reactive UI state updates.
     *
     * Always emits a user-prompt message. Adds an assistant message when the run completed
     * and emits an `agent` state update carrying runid/status/pending approvals for the
     * block_ai_chat frontend's approval card rendering.
     *
     * @param \local_ai_manager\agent\run_result $result The orchestrator outcome.
     * @param string $prompt Original user prompt (empty when resuming).
     * @param int $conversationid Conversation id for message metadata.
     * @return array List of state updates.
     */
    protected function convert_run_result_to_updates(
        \local_ai_manager\agent\run_result $result,
        string $prompt,
        int $conversationid,
    ): array {
        $updates = [];
        if ($prompt !== '') {
            $updates[] = [
                'name' => 'messages',
                'action' => 'put',
                'fields' => json_encode([
                    'id' => 'run-' . $result->runid . '-user',
                    'conversationid' => $conversationid,
                    'content' => s($prompt),
                    'sender' => 'user',
                    'messageMode' => 'toolagent',
                    'rendered' => false,
                ]),
            ];
        }
        if ($result->is_complete() && $result->final_text !== null) {
            $updates[] = [
                'name' => 'messages',
                'action' => 'put',
                'fields' => json_encode([
                    'id' => 'run-' . $result->runid . '-ai',
                    'conversationid' => $conversationid,
                    'content' => format_text($result->final_text, FORMAT_MARKDOWN, ['context' => $this->context]),
                    'sender' => 'ai',
                    'messageMode' => 'toolagent',
                    'rendered' => false,
                ]),
            ];
        }
        $reloadsuggested = $result->is_complete() && $this->has_successful_mutation($result->tool_results);
        $updates[] = [
            'name' => 'agent',
            'action' => 'put',
            'fields' => json_encode([
                'runid' => $result->runid,
                'status' => $result->status,
                'iterations' => $result->iterations,
                'errorCode' => $result->error_code,
                'errorMessage' => $result->error_message,
                'pendingApprovals' => $result->pending_approvals,
                'reloadSuggested' => $reloadsuggested,
            ]),
        ];
        return $updates;
    }

    /**
     * Convert a log entry into two message entries for the reactive UI.
     *
     * @param stdClass $logentry the log entry from 'local_ai_manager_request_log' table
     * @return array messages formatted as reactive UI state updates
     */
    public function convert_log_entry_to_messages(stdClass $logentry): array {
        $connectorfactory = \core\di::get(\local_ai_manager\local\connector_factory::class);
        $purpose = $connectorfactory->get_purpose_by_purpose_string($logentry->purpose);
        return [
            [
                'name' => 'messages',
                'action' => 'put',
                'fields' => json_encode([
                    'id' => $logentry->id . '-1',
                    'conversationid' => $logentry->itemid,
                    'content' => htmlspecialchars($logentry->prompttext),
                    'sender' => 'user',
                    'messageMode' => 'chat',
                    'rendered' => false,
                ]),
            ],
            [
                'name' => 'messages',
                'action' => 'put',
                'fields' => json_encode([

                    'id' => $logentry->id . '-2',
                    'conversationid' => $logentry->itemid,
                    'content' => $purpose->format_output($logentry->promptcompletion),
                    'sender' => 'ai',
                    'messageMode' => $logentry->purpose === 'agent' ? 'agent' : 'chat',
                    'rendered' => false,
                ]),
            ],
        ];
    }

    /**
     * Convert a completed tool-agent run into UI state updates so the conversation history
     * shows tool-agent turns after a page reload.
     *
     * @param \local_ai_manager\local\agent\entity\agent_run $run a completed agent run
     * @return array messages formatted as reactive UI state updates
     */
    protected function convert_agent_run_to_messages(
        \local_ai_manager\local\agent\entity\agent_run $run,
    ): array {
        $runid = (int) $run->get('id');
        $userprompt = (string) $run->get('user_prompt');
        $finaltext = (string) $run->get('final_text');
        $updates = [];
        if ($userprompt !== '') {
            $updates[] = [
                'name' => 'messages',
                'action' => 'put',
                'fields' => json_encode([
                    'id' => 'run-' . $runid . '-user',
                    'conversationid' => (int) $run->get('conversationid'),
                    'content' => s($userprompt),
                    'sender' => 'user',
                    'messageMode' => 'toolagent',
                    'rendered' => false,
                ]),
            ];
        }
        if ($finaltext !== '') {
            $updates[] = [
                'name' => 'messages',
                'action' => 'put',
                'fields' => json_encode([
                    'id' => 'run-' . $runid . '-ai',
                    'conversationid' => (int) $run->get('conversationid'),
                    'content' => format_text($finaltext, FORMAT_MARKDOWN, ['context' => $this->context]),
                    'sender' => 'ai',
                    'messageMode' => 'toolagent',
                    'rendered' => false,
                ]),
            ];
        }
        return $updates;
    }

    /**
     * Build the reactive `agent` state update carrying any still-pending approvals for a
     * conversation, so the approval cards re-appear after a page reload.
     *
     * Fresh HMAC tokens are issued for each awaiting tool_call — the client needs a valid,
     * unexpired token to submit the approve/reject external call.
     *
     * @param int $userid the id of the user
     * @param int $conversationid the id of the conversation
     * @return array|null a single state update, or null if nothing is pending
     */
    protected function build_pending_agent_state(int $userid, int $conversationid): ?array {
        if ($conversationid <= 0) {
            return null;
        }
        $runs = \local_ai_manager\local\agent\entity\agent_run::get_records(
            [
                'conversationid' => $conversationid,
                'userid' => $userid,
                'component' => $this->component,
                'contextid' => $this->context->id,
                'status' => \local_ai_manager\local\agent\entity\agent_run::STATUS_AWAITING_APPROVAL,
            ],
            'started',
            'DESC',
        );
        if (empty($runs)) {
            return null;
        }
        // Only the most recent awaiting run needs its card rendered — older ones were either
        // resumed already or are stale.
        $run = reset($runs);
        $runid = (int) $run->get('id');

        $calls = \local_ai_manager\local\agent\entity\tool_call::get_records(
            ['runid' => $runid],
            'callindex',
            'ASC',
        );
        $pending = [];
        $registry = \core\di::get(\local_ai_manager\agent\tool_registry::class);
        $tokenissuer = \local_ai_manager\agent\approval_token::instance();
        foreach ($calls as $call) {
            if ($call->get('approval_state') !== \local_ai_manager\local\agent\entity\tool_call::APPROVAL_AWAITING) {
                continue;
            }
            if ($call->get('result_json') !== null) {
                continue;
            }
            $toolname = (string) $call->get('toolname');
            $args = json_decode((string) $call->get('args_json'), true) ?: [];
            try {
                $tool = $registry->get_by_name($toolname);
                $describe = $tool->describe_for_user($args);
                $affected = $tool->get_affected_objects($args);
            } catch (\Throwable $e) {
                // Tool disappeared (e.g. disabled since issue) — show a fallback description.
                $describe = $toolname;
                $affected = [];
            }
            $token = $tokenissuer->issue(
                $runid,
                (int) $call->get('callindex'),
                $userid,
                (string) $call->get('args_hash'),
            );
            $pending[] = [
                'callid' => (int) $call->get('id'),
                'callindex' => (int) $call->get('callindex'),
                'tool' => $toolname,
                'args' => $args,
                'token' => $token,
                'describe' => $describe,
                'affected' => $affected,
                'dry_run' => null,
            ];
        }
        if (empty($pending)) {
            return null;
        }
        return [
            'name' => 'agent',
            'action' => 'put',
            'fields' => json_encode([
                'runid' => $runid,
                'status' => (string) $run->get('status'),
                'iterations' => (int) $run->get('iterations'),
                'errorCode' => null,
                'errorMessage' => null,
                'pendingApprovals' => $pending,
                'reloadSuggested' => false,
            ]),
        ];
    }

    /**
     * Check whether a run's tool_results contain at least one successful mutating tool call.
     *
     * Used to decide whether the UI should offer a "Reload page" hint so newly created or
     * updated course/module objects become visible in surrounding Moodle navigation.
     *
     * @param array $toolresults list of ['toolname' => string, 'ok' => bool, ...] entries
     * @return bool
     */
    protected function has_successful_mutation(array $toolresults): bool {
        foreach ($toolresults as $entry) {
            if (empty($entry['ok'])) {
                continue;
            }
            $toolname = (string) ($entry['toolname'] ?? '');
            if ($toolname === '') {
                continue;
            }
            // Mutating tool names follow the pattern *_create / *_update / *_delete.
            if (preg_match('/_(create|update|delete)$/', $toolname) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve the messages for a given conversation to be sent as context to the external AI system.
     *
     * @param int $itemid the conversation id
     * @param int $userid the id of the user
     * @param int $conversationlimit how many older messages should be retrieved
     * @return array formatted array of older messages, ready to be injected into the AI request as conversationcontext
     */
    public function retrieve_conversationcontext(int $itemid, int $userid, int $conversationlimit): array {
        $logentries = ai_manager_utils::get_log_entries(
            $this->component,
            $this->context->id,
            $userid,
            $itemid,
            false,
            'prompttext,promptcompletion',
            ['chat', 'agent'],
            $conversationlimit
        );

        $messages = [];
        foreach ($logentries as $logentry) {
            $messages[] = [
                'sender' => 'user',
                'message' => $logentry->prompttext,
            ];
            $messages[] = [
                'sender' => 'ai',
                'message' => $logentry->promptcompletion,
            ];
        }
        return $messages;
    }

    /**
     * Duplicate a persona.
     *
     * @param int $personaid The ID of the persona to duplicate.
     * @return array reactive UI state updates
     */
    public function duplicate_persona(int $personaid): array {
        global $USER, $DB;

        $clock = \core\di::get(\core\clock::class);
        $time = $clock->time();
        $personatoduplicate = $DB->get_record('block_ai_chat_personas', ['id' => $personaid]);
        if (!$personatoduplicate) {
            throw new moodle_exception('errorpersonanotfound', 'block_ai_chat');
        }
        unset($personatoduplicate->id);
        $personaobject = (object) [
            'name' => get_string('duplicatepersonaname', 'block_ai_chat', $personatoduplicate->name),
            'userid' => $USER->id,
            'prompt' => $personatoduplicate->prompt,
            'userinfo' => $personatoduplicate->userinfo,
            'type' => persona::TYPE_USER,
            'timemodified' => $time,
            'timecreated' => $time,
        ];

        $personaobject->id = $DB->insert_record('block_ai_chat_personas', $personaobject);

        $returnpersona = [
            'id' => $personaobject->id,
            'userid' => $personaobject->userid,
            'name' => $personaobject->name,
            'prompt' => $personaobject->prompt,
            'userinfo' => $personaobject->userinfo,
            'type' => $personaobject->type,
        ];

        return
            [
                'code' => 200,
                'content' =>
                    [
                        [
                            'name' => 'personas',
                            'action' => 'put',
                            'fields' => json_encode($returnpersona),
                        ],
                    ],
            ];
    }

    /**
     * Return the structure for a persona object.
     *
     * @return external_single_structure the persona object structure
     */
    public static function get_persona_structure(): external_single_structure {
        return new external_single_structure(
            [
                'id' => new external_value(PARAM_INT, 'persona id', VALUE_OPTIONAL),
                'userid' => new external_value(PARAM_INT, 'The user id'),
                'name' => new external_value(PARAM_RAW, 'The display name of the persona'),
                'prompt' => new external_value(PARAM_RAW, 'Prompt of the persona'),
                'userinfo' => new external_value(PARAM_RAW, 'The user info'),
                'type' => new external_value(PARAM_INT, 'The type of the persona'),
            ]
        );
    }

    /**
     * Return the structure for the initial state of the block_ai_chat reactive UI.
     *
     * @return array the initial state to be returned via external function
     */
    public function get_initial_state(): array {
        global $DB, $USER;
        $haseditcapability = has_capability('block/ai_chat:edit', $this->context);
        $conversationcontext =
            $DB->get_record('block_ai_chat_options', ['contextid' => $this->context->id, 'name' => 'historycontextmax']);
        $currentpersonaid = persona::get_current_persona_id($this->context->id);
        $aiconfig = ai_manager_utils::get_ai_config($USER, $this->context->id, null, ['agent']);
        $agentavailable = $aiconfig['purposes'][0]['available'] === ai_manager_utils::AVAILABILITY_AVAILABLE;
        $agentunavailablepagetypes = array_filter(
            array_map(
                'trim',
                explode(PHP_EOL, trim(get_config('block_ai_chat', 'agentmodeunavailablepagetypes')))
            ),
            fn($entry) => !empty($entry)
        );

        return [
            'static' => [
                'contextid' => $this->context->id,
                'component' => $this->component,
                'userid' => $USER->id,
                'showPersona' => $haseditcapability,
                'showOptions' => $haseditcapability,
                'showAgentMode' => has_capability('block/ai_chat:useagentmode', $this->context) && $agentavailable,
                'showToolagentMode' => has_capability('block/ai_chat:useagentmode', $this->context),
                'agentModeUnavailablePagetypes' => $agentunavailablepagetypes,
                'canEditSystemPersonas' => has_capability('block/ai_chat:managepersonatemplates', $this->context),
                'isAdmin' => is_siteadmin(),
                // Will be shown in the persona info modal, if it is present.
                // Provides additional information about what personas are and how they can be used.
                'personalink' => get_config('block_ai_chat', 'personalink') ?: null,
            ],
            'config' => [
                // The param 'windowMode' is initially null. JS will extract saved state from local storage
                // or set a default.
                'windowMode' => null,
                'mode' => 'chat', // Currently, there is only chat mode. Future modes might be 'agent' etc.
                // Current conversation id is being set to the latest conversation of the user in this context.
                'currentConversationId' => $this->get_latest_conversationid($USER->id),
                // If the chat in this context has a persona selected, we set it here. If not, it will be 0.
                'currentPersona' => $currentpersonaid,
                // The currently marked persona is the one shown in the persona management page. In the initial state
                // it needs to be identical to the 'currentPersona'.
                'currentlyMarkedPersona' => $currentpersonaid,
                'conversationContextLimit' => $conversationcontext ? $conversationcontext->value : 5,
                'loadingState' => false,
                // Initially, the view is null. Reactive UI main component will read last state from LocalStorage or
                // apply a default.
                'view' => null,
                'modalVisible' => false,
            ],
            // Will be lazy-loaded by the chat component, so state updates will directly trigger the
            // adding of messages in the UI.
            'messages' => [],
            'personas' => $this->get_personas(),
            // MBS-10761: Tool-agent run state slice. Populated by request_toolagent() via reactive updates.
            'agent' => [
                'runid' => 0,
                'status' => '',
                'iterations' => 0,
                'errorCode' => '',
                'errorMessage' => '',
                'pendingApprovals' => [],
            ],
        ];
    }

    /**
     * Return the structure for the initial state of the block_ai_chat reactive UI.
     *
     * @return external_single_structure the initial state structure
     */
    public static function get_initial_state_structure(): external_single_structure {
        return new external_single_structure([
            'static' => new external_single_structure([
                'contextid' => new external_value(PARAM_INT, 'Context ID'),
                'component' => new external_value(PARAM_COMPONENT, 'Component name of the plugin using block_ai_chat'),
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'showPersona' => new external_value(PARAM_BOOL, 'Configuring personas allowed'),
                'showOptions' => new external_value(PARAM_BOOL, 'Configuring options allowed'),
                'showAgentMode' => new external_value(PARAM_BOOL, 'Agent mode allowed'),
                'showToolagentMode' => new external_value(PARAM_BOOL, 'Tool-Agent mode allowed'),
                'agentModeUnavailablePagetypes' => new external_multiple_structure(
                    new external_value(
                        PARAM_RAW,
                        'Pagetype where agent mode is not available'
                    ),
                    'List of pagetypes where agent mode is not available'
                ),
                'canEditSystemPersonas' => new external_value(PARAM_BOOL, 'If user is allowed to edit system personas'),
                'isAdmin' => new external_value(PARAM_BOOL, 'If the user is site administrator'),
                'personalink' => new external_value(PARAM_RAW, 'External link with information about personas'),
            ]),
            'config' => new external_single_structure([
                'windowMode' => new external_value(PARAM_TEXT, 'Window mode'),
                'mode' => new external_value(PARAM_TEXT, 'Mode (e.g. chat)'),
                'currentConversationId' => new external_value(PARAM_INT, 'Current conversation ID'),
                'currentPersona' => new external_value(PARAM_INT, 'Current persona ID'),
                'currentlyMarkedPersona' => new external_value(PARAM_INT, 'Currently marked persona ID'),
                'conversationContextLimit' => new external_value(PARAM_INT, 'Context message limit'),
                'loadingState' => new external_value(PARAM_BOOL, 'Loading state'),
                'view' => new external_value(PARAM_RAW, 'Current view', VALUE_OPTIONAL),
                'modalVisible' => new external_value(PARAM_BOOL, 'Modal visible'),
            ]),
            'messages' => new external_multiple_structure(
                new external_value(PARAM_RAW, 'JSON encoded message object (lazy-loaded)'),
                'List of messages'
            ),
            'personas' => new external_multiple_structure(
                self::get_persona_structure(),
            ),
            'agent' => new external_single_structure([
                'runid' => new external_value(PARAM_INT, 'Current tool-agent run id (0 = none)'),
                'status' => new external_value(PARAM_ALPHANUMEXT, 'Run status'),
                'iterations' => new external_value(PARAM_INT, 'Number of iterations performed'),
                'errorCode' => new external_value(PARAM_ALPHANUMEXT, 'Stable error code'),
                'errorMessage' => new external_value(PARAM_RAW, 'User-facing error message'),
                'pendingApprovals' => new external_multiple_structure(
                    new external_single_structure([
                        'callindex' => new external_value(PARAM_INT, 'Call index within the run'),
                        'tool' => new external_value(PARAM_ALPHANUMEXT, 'Tool name'),
                        'describe' => new external_value(PARAM_RAW, 'User-facing description'),
                        'token' => new external_value(PARAM_RAW, 'HMAC approval token'),
                    ]),
                    'Pending tool call approvals'
                ),
            ]),
        ]);
    }

    /**
     * Check whether the user has the permission to manage the given persona.
     *
     * The following rules apply:
     * - Site administrators can always manage any persona.
     * - Global template personas (TYPE_TEMPLATE) require the 'managepersonatemplates' capability.
     * - If the user is the owner of the persona, they can manage it.
     * - Otherwise, a moodle_exception is thrown.
     *
     * @param int $personaid The ID of the persona to check.
     * @param int $userid The ID of the user requesting access.
     * @throws moodle_exception If the user is not allowed to manage the persona.
     */
    public function require_manage_persona(int $personaid, int $userid): void {
        global $DB;
        $persona = $DB->get_record('block_ai_chat_personas', ['id' => $personaid]);
        if (!$persona) {
            return;
        }

        if (intval($persona->type) === persona::TYPE_TEMPLATE) {
            require_capability('block/ai_chat:managepersonatemplates', $this->context);
        } else {
            if ($userid !== intval($persona->userid) && !is_siteadmin()) {
                throw new moodle_exception('error_managepersonanotallowed', 'block_ai_chat');
            }
        }
    }

    /**
     * Check whether the user has the permission to view the given persona.
     *
     * The following rules apply:
     * - Site administrators can always view any persona.
     * - Global template personas (TYPE_TEMPLATE) are always visible.
     * - If the persona is currently selected in this context, it is visible.
     * - If the user is the owner of the persona, it is visible.
     * - Otherwise, a moodle_exception is thrown.
     *
     * @param int $personaid The ID of the persona to check.
     * @param int $userid The ID of the user requesting access.
     * @throws moodle_exception If the user is not allowed to view the persona.
     */
    public function require_view_persona(int $personaid, int $userid): void {
        global $DB;
        $persona = $DB->get_record('block_ai_chat_personas', ['id' => $personaid]);
        if (!$persona) {
            return;
        }

        if (is_siteadmin()) {
            return;
        }

        if (intval($persona->type) === persona::TYPE_TEMPLATE) {
            // Global templates can always be viewed.
            return;
        }

        $personaselected =
            $DB->get_record('block_ai_chat_personas_selected', ['personasid' => $persona->id, 'contextid' => $this->context->id]);
        if ($personaselected && intval($personaselected->contextid) === $this->context->id) {
            return;
        }
        if ($userid === intval($persona->userid)) {
            return;
        }

        throw new moodle_exception('error_viewpersonanotallowed', 'block_ai_chat');
    }

    /**
     * Canonical conversation mode constants (MBS-10761).
     */
    public const MODE_CHAT = 'chat';
    /** @var string Form-assist mode (maps to local_ai_manager purpose "agent"). */
    public const MODE_FORMASSIST = 'formassist';
    /** @var string Tool-agent mode (dispatches to the orchestrator). */
    public const MODE_TOOLAGENT = 'toolagent';

    /**
     * Normalise the incoming mode name, accepting the legacy name `agent` as alias for `formassist`.
     *
     * @param string $mode Requested mode.
     * @return string Canonical mode identifier.
     * @throws moodle_exception When the mode is unknown.
     */
    public static function normalise_mode(string $mode): string {
        $canonical = match ($mode) {
            'chat' => self::MODE_CHAT,
            'agent', 'formassist' => self::MODE_FORMASSIST,
            'toolagent' => self::MODE_TOOLAGENT,
            default => null,
        };
        if ($canonical === null) {
            throw new moodle_exception('error_invalidmode', 'block_ai_chat', '', $mode);
        }
        return $canonical;
    }

    /**
     * Decide whether a transition between two locked conversation modes is allowed (MBS-10761).
     *
     * The mode is locked at the first user turn. Subsequent turns must use the same mode.
     *
     * @param string $stored Mode recorded on the first turn.
     * @param string $requested Mode requested on a subsequent turn.
     * @return bool True when the requested mode is compatible with the stored mode.
     */
    public static function is_mode_transition_allowed(string $stored, string $requested): bool {
        return $stored === $requested;
    }

    /**
     * Check the capability matching the requested mode (MBS-10761).
     *
     * @param string $mode Canonical mode.
     */
    protected function require_mode_capability(string $mode): void {
        switch ($mode) {
            case self::MODE_CHAT:
                require_capability('block/ai_chat:view', $this->context);
                break;
            case self::MODE_FORMASSIST:
                // Accept either the dedicated new capability or the legacy one for BC.
                if (!has_capability('block/ai_chat:useformassist', $this->context)
                        && !has_capability('block/ai_chat:useagentmode', $this->context)) {
                    throw new \required_capability_exception(
                        $this->context,
                        'block/ai_chat:useformassist',
                        'nopermissions',
                        ''
                    );
                }
                break;
            case self::MODE_TOOLAGENT:
                if (!has_capability('block/ai_chat:useagent', $this->context)
                        && !has_capability('block/ai_chat:useagentmode', $this->context)) {
                    throw new \required_capability_exception(
                        $this->context,
                        'block/ai_chat:useagent',
                        'nopermissions',
                        ''
                    );
                }
                break;
        }
    }

    /**
     * Ensure the conversation's locked mode matches the requested mode; lock on first turn (MBS-10761).
     *
     * Returns a reactive error payload when the stored mode differs from the requested mode, otherwise
     * inserts (or reuses) a row in `block_ai_chat_conversations` and returns `null` to signal success.
     *
     * @param int $conversationid Conversation / itemid grouping value.
     * @param int $userid User initiating the turn.
     * @param string $mode Canonical requested mode.
     * @return array|null Null on success; on lock violation a payload with `code`/`message`/`content`.
     */
    protected function ensure_conversation_mode(int $conversationid, int $userid, string $mode): ?array {
        global $DB;
        $existing = $DB->get_record(
            'block_ai_chat_conversations',
            ['contextid' => $this->context->id, 'conversationid' => $conversationid],
            'id, agent_mode',
            IGNORE_MISSING
        );
        if ($existing) {
            if (!self::is_mode_transition_allowed($existing->agent_mode, $mode)) {
                $a = (object) ['stored' => $existing->agent_mode, 'requested' => $mode];
                return [
                    'code' => 423,
                    'message' => get_string('error_conversationmodelocked', 'block_ai_chat', $a),
                    'debuginfo' => '',
                    'content' => [],
                ];
            }
            return null;
        }
        $record = (object) [
            'contextid' => $this->context->id,
            'conversationid' => $conversationid,
            'userid' => $userid,
            'agent_mode' => $mode,
            'timecreated' => time(),
        ];
        $DB->insert_record('block_ai_chat_conversations', $record);
        return null;
    }
}
