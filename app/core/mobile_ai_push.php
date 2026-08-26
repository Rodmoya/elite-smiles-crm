<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mobile_ai_auth.php';

if (!function_exists('mobile_ai_web_push_ready')) {
    function mobile_ai_web_push_ready(): bool
    {
        $publicKey = defined('ELITE_WEB_PUSH_PUBLIC_KEY') ? trim((string) ELITE_WEB_PUSH_PUBLIC_KEY) : '';
        $privateKey = defined('ELITE_WEB_PUSH_PRIVATE_KEY') ? trim((string) ELITE_WEB_PUSH_PRIVATE_KEY) : '';

        return $publicKey !== ''
            && $privateKey !== ''
            && is_file(ROOT_PATH . '/vendor/autoload.php');
    }
}

if (!function_exists('mobile_ai_unread_badge_count')) {
    function mobile_ai_unread_badge_count(): int
    {
        try {
            $row = db_one(
                "SELECT COUNT(*) AS total
                 FROM lead_messages
                 WHERE direction = 'inbound'
                   AND COALESCE(is_read, 0) = 0"
            );
            $total = max(0, (int) ($row['total'] ?? 0));
            try {
                $testRow = db_one(
                    "SELECT COUNT(*) AS total
                     FROM elite_ai_test_notifications
                     WHERE COALESCE(is_read, 0) = 0
                       AND (expires_at IS NULL OR expires_at >= NOW())"
                );
                $total += max(0, (int) ($testRow['total'] ?? 0));
            } catch (Throwable $e) {
                // The Elite AI test-notification table is optional.
            }
            return $total;
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('mobile_ai_send_push_payload')) {
    function mobile_ai_send_push_payload(array $payload, int $userId = 0): array
    {
        $result = [
            'configured' => mobile_ai_web_push_ready(),
            'attempted' => 0,
            'sent' => 0,
            'failed' => 0,
            'expired' => 0,
        ];
        if (!$result['configured']) {
            return $result;
        }

        require_once ROOT_PATH . '/vendor/autoload.php';
        mobile_ai_ensure_schema();

        $where = [
            'enabled = 1',
            'push_enabled = 1',
            'revoked_at IS NULL',
        ];
        $params = [];
        if ($userId > 0) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $rows = db_all(
            'SELECT id, endpoint, subscription_json
             FROM user_push_subscriptions
             WHERE ' . implode(' AND ', $where),
            $params
        );
        if (!$rows) {
            return $result;
        }

        $payload['title'] = trim((string) ($payload['title'] ?? 'Elite AI')) ?: 'Elite AI';
        $payload['push_body'] = trim((string) ($payload['push_body'] ?? $payload['body'] ?? 'New CRM activity.'));
        $payload['badge_count'] = max(0, (int) ($payload['badge_count'] ?? mobile_ai_unread_badge_count()));
        $payload['url'] = trim((string) ($payload['url'] ?? '/crm/mobile-ai?tab=notifications'));
        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($payloadJson) || $payloadJson === '') {
            return $result;
        }

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => defined('ELITE_WEB_PUSH_SUBJECT') ? trim((string) ELITE_WEB_PUSH_SUBJECT) : '',
                'publicKey' => trim((string) ELITE_WEB_PUSH_PUBLIC_KEY),
                'privateKey' => trim((string) ELITE_WEB_PUSH_PRIVATE_KEY),
            ],
        ], [
            'TTL' => 21600,
            'urgency' => 'high',
            'batchSize' => 50,
            'contentType' => 'application/json',
        ]);
        $webPush->setReuseVAPIDHeaders(true);

        $rowByEndpoint = [];
        foreach ($rows as $row) {
            $subscriptionData = json_decode((string) ($row['subscription_json'] ?? ''), true);
            if (!is_array($subscriptionData) || empty($subscriptionData['endpoint'])) {
                continue;
            }
            try {
                $subscription = \Minishlink\WebPush\Subscription::create($subscriptionData);
                $webPush->queueNotification($subscription, $payloadJson);
                $rowByEndpoint[(string) $subscriptionData['endpoint']] = (int) ($row['id'] ?? 0);
                $result['attempted']++;
            } catch (Throwable $e) {
                $result['failed']++;
                esm_log('mobile_ai_push', 'Invalid push subscription skipped.', [
                    'subscription_id' => (int) ($row['id'] ?? 0),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $result['sent']++;
                    continue;
                }
                $result['failed']++;
                if ($report->isSubscriptionExpired()) {
                    $result['expired']++;
                    $subscriptionId = (int) ($rowByEndpoint[$report->getEndpoint()] ?? 0);
                    if ($subscriptionId > 0) {
                        db_execute(
                            'UPDATE user_push_subscriptions
                             SET enabled = 0, push_enabled = 0, revoked_at = NOW(), updated_at = NOW()
                             WHERE id = :id',
                            ['id' => $subscriptionId]
                        );
                    }
                }
                esm_log('mobile_ai_push', 'Web Push delivery failed.', [
                    'endpoint_host' => (string) parse_url($report->getEndpoint(), PHP_URL_HOST),
                    'reason' => $report->getReason(),
                ]);
            }
        } catch (Throwable $e) {
            $result['failed'] += max(0, $result['attempted'] - $result['sent'] - $result['failed']);
            esm_log('mobile_ai_push', 'Web Push flush failed.', ['error' => $e->getMessage()]);
        }

        return $result;
    }
}

if (!function_exists('mobile_ai_send_lead_event_push')) {
    function mobile_ai_send_lead_event_push(array $lead, array $context = []): array
    {
        $leadId = (int) ($context['lead_id'] ?? $lead['id'] ?? 0);
        $leadName = trim((string) ($lead['full_name'] ?? 'Lead')) ?: 'Lead';
        $type = strtolower(trim((string) ($context['type'] ?? 'reply')));
        $message = trim((string) ($context['message'] ?? $context['note'] ?? ''));
        $notificationId = trim((string) ($context['notification_id'] ?? ''));
        $url = '/crm/mobile-ai?tab=assistant&lead_id=' . $leadId;
        if ($notificationId !== '') {
            $url .= '&notification_id=' . rawurlencode($notificationId);
        }

        if ($type === 'new_lead') {
            $source = trim((string) ($context['source_label'] ?? 'Meta Lead Form'));
            $firstTouchSent = !empty($context['first_touch_sent']);
            $body = 'Rod, new lead from ' . $source . ': ' . $leadName . '. '
                . ($firstTouchSent ? 'First message sent.' : 'First message needs review.');
        } elseif ($type === 'stop') {
            $body = 'Rod, ' . $leadName . ' replied STOP. SMS is blocked. Open Elite AI and tell me what to do.';
        } elseif ($type === 'handoff') {
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', $message) ?? $message, 0, 130);
            $body = 'Rod, scheduling follow-up needed for ' . $leadName . ($excerpt !== '' ? ': "' . $excerpt . '"' : '.');
        } else {
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', $message) ?? $message, 0, 130);
            $body = 'Rod, new message from ' . $leadName . ($excerpt !== '' ? ': "' . $excerpt . '"' : '.');
        }

        return mobile_ai_send_push_payload([
            'title' => 'Elite AI',
            'push_body' => $body,
            'tag' => $notificationId !== '' ? 'elite-ai-' . preg_replace('/[^a-z0-9_-]+/i', '-', $notificationId) : 'elite-ai-lead-' . $leadId,
            'url' => $url,
            'lead_id' => $leadId,
            'notification_id' => $notificationId,
            'badge_count' => mobile_ai_unread_badge_count(),
            'data' => ['url' => $url],
        ]);
    }
}
