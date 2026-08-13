<?php
declare(strict_types=1);

if (!function_exists('elite_ai_tool_registry')) {
    function elite_ai_tool_registry(): array
    {
        return [
            'lead.get' => [
                'label' => 'Read lead',
                'description' => 'Load one CRM lead with conversion metadata.',
                'mode' => 'read',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'lead.thread' => [
                'label' => 'Read conversation',
                'description' => 'Load recent SMS, email, and activity timeline for one lead.',
                'mode' => 'read',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'lead.search' => [
                'label' => 'Search leads',
                'description' => 'Find leads by name, email, or phone.',
                'mode' => 'read',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'pipeline.snapshot' => [
                'label' => 'Pipeline snapshot',
                'description' => 'Read a compact view of current pipeline stages and counts.',
                'mode' => 'read',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'memory.search' => [
                'label' => 'Search learned memory',
                'description' => 'Retrieve relevant learned rules, preferences, and prior assistant experience.',
                'mode' => 'read',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'memory.remember' => [
                'label' => 'Remember instruction',
                'description' => 'Save a durable CRM assistant memory for future tasks.',
                'mode' => 'write',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'lead.draft_sms' => [
                'label' => 'Draft SMS',
                'description' => 'Create an SMS draft in the human approval queue. Does not send.',
                'mode' => 'draft',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => true,
                'approval' => 'sms_send_approved',
            ],
            'lead.draft_email' => [
                'label' => 'Draft email',
                'description' => 'Create an email draft in the human approval queue. Does not send.',
                'mode' => 'draft',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => true,
                'approval' => 'email_send_approved',
            ],
            'lead.add_note' => [
                'label' => 'Add note',
                'description' => 'Add an internal note to a lead timeline.',
                'mode' => 'write',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
            'lead.move_stage' => [
                'label' => 'Move stage',
                'description' => 'Move a lead to another existing pipeline stage.',
                'mode' => 'write',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => true,
                'approval' => 'stage_approved',
            ],
            'notifications.mark_reviewed' => [
                'label' => 'Mark notification reviewed',
                'description' => 'Clear a notification after operator review.',
                'mode' => 'write',
                'surfaces' => ['desktop', 'mobile'],
                'approval_required' => false,
            ],
        ];
    }
}

if (!function_exists('elite_ai_tool_capabilities')) {
    function elite_ai_tool_capabilities(?string $surface = null): array
    {
        $surface = $surface !== null ? strtolower(trim($surface)) : null;
        $tools = [];
        foreach (elite_ai_tool_registry() as $name => $definition) {
            $surfaces = array_values((array) ($definition['surfaces'] ?? []));
            if ($surface !== null && $surface !== '' && !in_array($surface, $surfaces, true)) {
                continue;
            }

            $tools[] = [
                'name' => $name,
                'label' => (string) ($definition['label'] ?? $name),
                'description' => (string) ($definition['description'] ?? ''),
                'mode' => (string) ($definition['mode'] ?? 'read'),
                'approval_required' => !empty($definition['approval_required']),
                'approval' => (string) ($definition['approval'] ?? ''),
            ];
        }

        return $tools;
    }
}

if (!function_exists('elite_ai_tool_user_label')) {
    function elite_ai_tool_user_label(array $user): string
    {
        $first = trim((string) ($user['first_name'] ?? ''));
        $last = trim((string) ($user['last_name'] ?? ''));
        $label = trim($first . ' ' . $last);
        return $label !== '' ? $label : 'Elite AI';
    }
}

if (!function_exists('elite_ai_tool_run')) {
    function elite_ai_tool_run(array $user, string $toolName, array $input = [], array $context = []): array
    {
        $toolName = strtolower(trim($toolName));
        $registry = elite_ai_tool_registry();
        if (!isset($registry[$toolName])) {
            return [
                'ok' => false,
                'tool' => $toolName,
                'message' => 'Unsupported Elite AI tool.',
            ];
        }

        $surface = function_exists('elite_ai_surface') ? elite_ai_surface(['surface' => ($input['surface'] ?? $context['surface'] ?? 'desktop')]) : 'desktop';

        try {
            switch ($toolName) {
                case 'lead.get':
                    $leadId = (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0);
                    $lead = function_exists('elite_ai_load_lead') ? elite_ai_load_lead($leadId) : null;
                    return [
                        'ok' => $lead !== null,
                        'tool' => $toolName,
                        'lead_id' => $leadId,
                        'lead' => $lead,
                        'message' => $lead ? 'Lead loaded.' : 'Lead not found.',
                    ];

                case 'lead.thread':
                    $leadId = (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0);
                    if ($leadId <= 0 || !function_exists('elite_ai_lead_thread')) {
                        return ['ok' => false, 'tool' => $toolName, 'message' => 'Lead conversation is not available.'];
                    }
                    return [
                        'ok' => true,
                        'tool' => $toolName,
                        'lead_id' => $leadId,
                        'thread' => elite_ai_lead_thread($leadId),
                        'message' => 'Conversation loaded.',
                    ];

                case 'lead.search':
                    $query = trim((string) ($input['query'] ?? $input['q'] ?? ''));
                    $limit = (int) ($input['limit'] ?? 8);
                    return [
                        'ok' => true,
                        'tool' => $toolName,
                        'query' => $query,
                        'matches' => function_exists('elite_ai_find_leads') ? elite_ai_find_leads($query, $limit) : [],
                        'message' => 'Lead search completed.',
                    ];

                case 'pipeline.snapshot':
                    return elite_ai_tool_pipeline_snapshot($toolName);

                case 'memory.search':
                    $query = trim((string) ($input['query'] ?? $input['prompt'] ?? ''));
                    $limit = (int) ($input['limit'] ?? 5);
                    return [
                        'ok' => true,
                        'tool' => $toolName,
                        'memories' => function_exists('elite_ai_memory_relevant')
                            ? elite_ai_memory_relevant($query, $context, $limit)
                            : [],
                        'message' => 'Learned memory search completed.',
                    ];

                case 'memory.remember':
                    if (!function_exists('elite_ai_memory_remember')) {
                        return ['ok' => false, 'tool' => $toolName, 'message' => 'Elite AI memory is not available right now.'];
                    }
                    $title = trim((string) ($input['title'] ?? 'Operator preference'));
                    $body = trim((string) ($input['body'] ?? $input['instruction'] ?? $input['prompt'] ?? ''));
                    $memoryId = elite_ai_memory_remember(
                        (string) ($input['memory_type'] ?? 'preference'),
                        $title,
                        $body,
                        (array) ($input['tags'] ?? []),
                        (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0) ?: null,
                        (float) ($input['confidence'] ?? 0.85)
                    );
                    return [
                        'ok' => $memoryId > 0,
                        'tool' => $toolName,
                        'memory_id' => $memoryId,
                        'message' => $memoryId > 0 ? 'Saved to Elite AI learned memory.' : 'Nothing was saved to memory.',
                    ];

                case 'lead.move_stage':
                    if (!function_exists('elite_ai_handle_move_stage_action')) {
                        return ['ok' => false, 'tool' => $toolName, 'message' => 'Stage changes are not available right now.'];
                    }
                    $request = $input + [
                        'surface' => $surface,
                        'lead_id' => (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0),
                        'context' => $context,
                    ];
                    return ['tool' => $toolName] + elite_ai_handle_move_stage_action($user, $request, $surface);

                case 'lead.add_note':
                    if (!function_exists('elite_ai_handle_add_note_action')) {
                        return ['ok' => false, 'tool' => $toolName, 'message' => 'Notes are not available right now.'];
                    }
                    $request = $input + [
                        'surface' => $surface,
                        'lead_id' => (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0),
                        'context' => $context,
                    ];
                    return ['tool' => $toolName] + elite_ai_handle_add_note_action($user, $request, $surface);

                case 'lead.draft_sms':
                case 'lead.draft_email':
                    if (!function_exists('elite_ai_prepare_action_draft')) {
                        return ['ok' => false, 'tool' => $toolName, 'message' => 'Drafting is not available right now.'];
                    }
                    $request = $input + [
                        'surface' => $surface,
                        'assistant_action' => $toolName === 'lead.draft_sms' ? 'draft_sms' : 'draft_email',
                        'lead_id' => (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0),
                        'context' => $context,
                    ];
                    return ['tool' => $toolName] + elite_ai_prepare_action_draft($user, $request, $surface);

                case 'notifications.mark_reviewed':
                    if (!function_exists('elite_ai_mark_notification_reviewed_payload')) {
                        return ['ok' => false, 'tool' => $toolName, 'message' => 'Notification review is not available right now.'];
                    }
                    $leadId = (int) ($input['lead_id'] ?? $context['lead_id'] ?? 0);
                    return ['tool' => $toolName] + elite_ai_mark_notification_reviewed_payload($user, $leadId, (string) ($input['source'] ?? 'elite_ai_tool'));
            }
        } catch (Throwable $e) {
            if (function_exists('esm_log')) {
                esm_log('elite_ai', 'Elite AI tool failed.', [
                    'tool' => $toolName,
                    'error' => $e->getMessage(),
                ]);
            }

            return [
                'ok' => false,
                'tool' => $toolName,
                'message' => 'Elite AI tool failed: ' . $e->getMessage(),
            ];
        }

        return [
            'ok' => false,
            'tool' => $toolName,
            'message' => 'Elite AI tool did not return a result.',
        ];
    }
}

if (!function_exists('elite_ai_tool_pipeline_snapshot')) {
    function elite_ai_tool_pipeline_snapshot(string $toolName): array
    {
        if (function_exists('lead_pipeline_counts')) {
            $counts = lead_pipeline_counts();
        } else {
            $counts = [];
            foreach (db_all('SELECT status, COUNT(*) AS total FROM leads GROUP BY status') as $row) {
                $counts[(string) ($row['status'] ?? '')] = (int) ($row['total'] ?? 0);
            }
        }

        $labels = function_exists('lead_stage_labels') ? lead_stage_labels() : [];
        $stages = [];
        foreach ($counts as $stage => $count) {
            $stageKey = (string) $stage;
            $stages[] = [
                'stage' => $stageKey,
                'label' => (string) ($labels[$stageKey] ?? $stageKey),
                'count' => (int) $count,
            ];
        }

        return [
            'ok' => true,
            'tool' => $toolName,
            'stages' => $stages,
            'message' => 'Pipeline snapshot loaded.',
        ];
    }
}
