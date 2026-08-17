<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/notifications/internal_sms.php';

function internal_sms_expect(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$defaults = internal_sms_default_recipients();
$rod = array_values(array_filter($defaults, static fn(array $recipient): bool => ($recipient['key'] ?? '') === 'rod_moya'))[0] ?? [];
internal_sms_expect(internal_sms_normalize_phone((string) ($rod['phone'] ?? '')) === '+18016037011', 'Rod default alert number is incorrect.');

$upgrade = internal_sms_upgrade_legacy_recipients([
    ['key' => 'rod_moya', 'name' => 'Rod Moya', 'phone' => '8014994831', 'enabled' => true],
    ['key' => 'dr_meden', 'name' => 'Dr. Meden', 'phone' => '8016887200', 'enabled' => true],
]);
internal_sms_expect(!empty($upgrade['changed']), 'Legacy Rod alert number was not detected.');
internal_sms_expect((string) ($upgrade['recipients'][0]['phone'] ?? '') === '+18016037011', 'Legacy Rod alert number was not migrated.');
internal_sms_expect((string) ($upgrade['recipients'][1]['phone'] ?? '') === '8016887200', 'Unrelated recipients must remain unchanged.');

$custom = internal_sms_upgrade_legacy_recipients([
    ['key' => 'rod_moya', 'name' => 'Rod Moya', 'phone' => '8015551212', 'enabled' => true],
]);
internal_sms_expect(empty($custom['changed']), 'A custom Rod alert number must not be overwritten.');

echo "Internal SMS recipient tests passed.\n";
