<?php
declare(strict_types=1);

function internal_notification_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$internalSms = (string) file_get_contents($root . '/app/notifications/internal_sms.php');
$leadAgent = (string) file_get_contents($root . '/app/leads/lead_agent.php');
$observability = (string) file_get_contents($root . '/app/leads/lead_agent_observability.php');
$inboundSms = (string) file_get_contents($root . '/app/api/twilio_sms_webhook.php');
$inboundEmail = (string) file_get_contents($root . '/app/leads/lead_email.php');
$statusCallback = (string) file_get_contents($root . '/app/api/twilio_sms_status.php');

internal_notification_expect(
    str_contains($internalSms, "'StatusCallback' => rtrim(APP_URL, '/') . '/app/api/twilio_sms_status.php'"),
    'Internal Twilio alerts must request delivery status callbacks.'
);
internal_notification_expect(
    str_contains($leadAgent, "internal_sms_find_recipient('rod_moya')")
        && str_contains($leadAgent, "empty(\$internal['ok']) && function_exists('elite_send_pushover_notification')"),
    'Lead Agent handoffs must use Rod SMS first and Pushover only after an immediate Twilio failure.'
);
internal_notification_expect(
    str_contains($observability, "empty(\$sms['ok']) && function_exists('elite_send_pushover_notification')"),
    'Lead Agent health alerts must not duplicate successful Twilio SMS with Pushover.'
);
internal_notification_expect(
    !str_contains($inboundSms, 'elite_send_operator_follow_up_pushover'),
    'Routine inbound SMS must not produce a Pushover alert.'
);
internal_notification_expect(
    !str_contains($inboundEmail, 'elite_send_operator_follow_up_pushover'),
    'Routine inbound email must not produce a Pushover alert.'
);
internal_notification_expect(
    str_contains($statusCallback, 'SELECT * FROM internal_sms_logs WHERE twilio_sid = :sid LIMIT 1')
        && str_contains($statusCallback, "'Internal SMS delivery failed'"),
    'Internal SMS delivery status must be audited with a Pushover fallback on asynchronous failure.'
);
internal_notification_expect(
    !str_contains($statusCallback, "'event' => 'sms_delivery_issue'"),
    'Patient SMS delivery failures must stay in CRM activity instead of creating routine Pushover noise.'
);

echo "Internal notification routing tests passed.\n";
