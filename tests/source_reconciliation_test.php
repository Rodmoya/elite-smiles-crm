<?php
declare(strict_types=1);

function reconciliation_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$read = static function (string $relative) use ($root): string {
    $contents = file_get_contents($root . '/' . $relative);
    reconciliation_expect(is_string($contents), 'Could not read ' . $relative . '.');
    return $contents;
};

$lab = $read('elite-ai-lab.php');
foreach (['Rodrigo Moya', 'Corey Nance', 'Maalona'] as $privateFixture) {
    reconciliation_expect(!str_contains($lab, $privateFixture), 'Elite AI Lab must use synthetic fixtures, not ' . $privateFixture . '.');
}

$service = $read('app/ai/elite_ai_service.php');
reconciliation_expect(!str_contains($service, 'Rodrigo is missing DOB'), 'Scheduling responses must use the selected lead name.');
reconciliation_expect(str_contains($service, "'source' => 'elite_ai'"), 'Production scheduling activity must use the Elite AI source.');

$processor = $read('app/api/meta_webhook_process.php');
reconciliation_expect(str_contains($processor, "if (!defined('META_PROCESSOR_LIBRARY_ONLY')) {"), 'Embedded Meta processing must not emit response headers.');

$mailer = $read('app/core/mailer.php');
$pushoverStart = strpos($mailer, "if (!function_exists('elite_send_lead_notification_pushover'))");
$pushoverEnd = strpos($mailer, "if (!function_exists('elite_operator_quick_action_url'))", $pushoverStart ?: 0);
$pushoverBlock = $pushoverStart !== false && $pushoverEnd !== false ? substr($mailer, $pushoverStart, $pushoverEnd - $pushoverStart) : '';
reconciliation_expect(str_contains($pushoverBlock, '$quickActionUrl = elite_quick_action_url($lead, $context);'), 'Lead Pushover notification must initialize its quick-action URL.');

$push = $read('app/core/mobile_ai_push.php');
reconciliation_expect(str_contains($push, "defined('ELITE_WEB_PUSH_PUBLIC_KEY')"), 'Mobile push must tolerate configuration-version skew.');

$workflow = $read('.github/workflows/deploy.yml');
reconciliation_expect(str_contains($workflow, 'composer install --no-interaction'), 'Deployment must install locked Composer dependencies.');
reconciliation_expect(str_contains($workflow, "github.ref == 'refs/heads/main'"), 'Production deployment must remain restricted to main.');
reconciliation_expect(str_contains($workflow, "github.event_name == 'workflow_dispatch' && inputs.deploy"), 'Manual production deployment must require explicit confirmation.');
reconciliation_expect(!str_contains($workflow, "github.event_name == 'pull_request'"), 'Pull-request validation must not deploy production.');

echo "Source reconciliation safety tests passed.\n";
