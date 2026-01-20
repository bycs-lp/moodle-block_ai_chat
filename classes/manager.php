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
    /** @var context the current context. */
    private context $context;

    /**
     * Class contructor.
     *
     * @param int $contextid The context id of the AI chat instance.
     */
    public function __construct(int $contextid) {
        $this->context = \context_helper::instance_by_id($contextid);
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
            'block_ai_chat',
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
            'userinfo' => $data->userinfo,
            'type' => is_null($data->type) ? $currentrecord->type : $data->type,
            'timemodified' => time(),
        ];

        $DB->update_record('block_ai_chat_personas', $personaobject);

        $returnpersona = [
            'id' => $personaobject->id,
            'userid' => $personaobject->userid,
            'name' => $personaobject->name,
            'prompt' => format_text($personaobject->prompt, FORMAT_MOODLE, ['para' => false]),
            'userinfo' => format_text($personaobject->userinfo, FORMAT_MOODLE, ['para' => false]),
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
            'block_ai_chat',
            $this->context->id,
            $userid,
            $conversationid,
            false,
            '*',
            ['chat', 'agent']
        );
        $messages = [];
        // Go over all log entries and create conversation items.
        foreach ($logentries as $logentry) {
            $messages = array_merge($messages, $this->convert_log_entry_to_messages($logentry, $logentry->purpose));
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
            'block_ai_chat',
            $this->context->id,
            $userid,
            0,
            false,
            'itemid',
            ['chat', 'agent'],
            1
        );
        if (empty($logentries)) {
            return 0;
        }
        $entry = array_values($logentries)[0];
        if (empty($entry->itemid)) {
            return 0;
        }
        return $entry->itemid;
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

        $aimanager = new \local_ai_manager\manager($mode);
        $requestresult = $aimanager->perform_request($prompt, 'block_ai_chat', $this->context->id, $options);
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
     * Retrieve the messages for a given conversation to be sent as context to the external AI system.
     *
     * @param int $itemid the conversation id
     * @param int $userid the id of the user
     * @param int $conversationlimit how many older messages should be retrieved
     * @return array formatted array of older messages, ready to be injected into the AI request as conversationcontext
     */
    public function retrieve_conversationcontext(int $itemid, int $userid, int $conversationlimit): array {
        $logentries = ai_manager_utils::get_log_entries(
            'block_ai_chat',
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

        return [
            'static' => [
                'contextid' => $this->context->id,
                'userid' => $USER->id,
                'showPersona' => $haseditcapability,
                'showOptions' => $haseditcapability,
                'showAgentMode' => has_capability('block/ai_chat:useagentmode', $this->context) && $agentavailable,
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
                'userid' => new external_value(PARAM_INT, 'User ID'),
                'showPersona' => new external_value(PARAM_BOOL, 'Configuring personas allowed'),
                'showOptions' => new external_value(PARAM_BOOL, 'Configuring options allowed'),
                'showAgentMode' => new external_value(PARAM_BOOL, 'Agent mode allowed'),
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
}
