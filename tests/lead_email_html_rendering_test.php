<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/leads/lead_email.php';

function lead_email_html_expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$appleMime = <<<'MIME'
--Apple-Mail-TEST-BOUNDARY
Content-Type: text/html;
	charset=utf-8
Content-Transfer-Encoding: quoted-printable

<html><body>Afternoon&nbsp;<br><div>Sent from my iPhone</div><script>alert(1)</script><img src=3D"https://hi.elitesmilesutah.com/crm/app/api/email_open.php?t=3Dbad" width=3D"1" height=3D"1"><blockquote><div><table style=3D"width:100%;background:#fff"><tr><td>Original Elite Smiles email</td></tr></table></div></blockquote></body></html>=
--Apple-Mail-TEST-BOUNDARY--
MIME;

$preparedApple = lead_email_prepare_content($appleMime);
lead_email_html_expect(str_contains($preparedApple['text'], 'Afternoon'), 'Apple Mail reply text was not decoded.');
lead_email_html_expect(str_contains($preparedApple['html'], 'Sent from my iPhone'), 'Apple Mail HTML formatting was not preserved.');
lead_email_html_expect(str_contains($preparedApple['html'], '<table'), 'Safe email table formatting was removed.');
lead_email_html_expect(!str_contains($preparedApple['html'], 'Apple-Mail-TEST-BOUNDARY'), 'Raw MIME boundary leaked into display HTML.');
lead_email_html_expect(!str_contains($preparedApple['html'], '=3D'), 'Quoted-printable encoding leaked into display HTML.');
lead_email_html_expect(!str_contains(strtolower($preparedApple['html']), '<script'), 'Script tag survived email sanitization.');
lead_email_html_expect(!str_contains($preparedApple['html'], 'email_open.php'), 'Tracking pixel survived email sanitization.');

$outbound = <<<'HTML'
<!doctype html><html><head><style>body{display:none}</style></head><body>
<table width="100%" style="background:#f4f7fb"><tr><td>
<img src="https://hi.elitesmilesutah.com/crm/assets/img/ES-Logo-Stack-500-x-150-px.png" width="210" alt="Elite Smiles">
<p style="font-size:16px;color:#334155" onclick="alert(1)">Hi Dallas,</p>
<a href="https://hi.elitesmilesutah.com/crm/app/api/email_unsubscribe.php?t=bad">unsubscribe</a>
</td></tr></table><iframe src="https://example.com"></iframe></body></html>
HTML;

$preparedOutbound = lead_email_prepare_content('Hi Dallas,', $outbound);
lead_email_html_expect(str_contains($preparedOutbound['html'], '<table'), 'Outbound email layout was not preserved.');
lead_email_html_expect(str_contains($preparedOutbound['html'], 'ES-Logo-Stack-500-x-150-px.png'), 'Approved Elite Smiles logo was removed.');
lead_email_html_expect(!str_contains(strtolower($preparedOutbound['html']), 'onclick'), 'Event handler survived sanitization.');
lead_email_html_expect(!str_contains(strtolower($preparedOutbound['html']), '<iframe'), 'Iframe survived sanitization.');
lead_email_html_expect(!preg_match('/href="[^"]*unsubscribe/i', $preparedOutbound['html']), 'Unsubscribe action remained clickable inside CRM history.');

$plain = lead_email_prepare_content("Hello\nTomorrow afternoon works.");
lead_email_html_expect(str_contains($plain['html'], 'Tomorrow afternoon works.'), 'Plain-text email did not receive a safe HTML presentation.');

$testLeadId = 0;
$testEmailId = 0;
try {
    $testAddress = 'email-html-' . bin2hex(random_bytes(5)) . '@example.invalid';
    $testLeadId = db_insert(
        "INSERT INTO leads (full_name, email, status, created_at, updated_at)
         VALUES ('Email HTML Rendering Test', :email, 'contacted', NOW(), NOW())",
        ['email' => $testAddress]
    );
    $testEmailId = lead_email_insert([
        'lead_id' => $testLeadId,
        'direction' => 'outbound',
        'from_email' => 'hello@hi.elitesmilesutah.com',
        'to_email' => $testAddress,
        'subject' => 'Existing email rendering test',
        'body' => 'Would mornings or afternoons be easier?',
        'tracking_token' => 'test-token',
        'status' => 'sent',
        'created_by' => 'Test',
    ]);
    $history = lead_email_recent($testLeadId, 5);
    lead_email_html_expect(count($history) === 1, 'Existing outbound email was not returned.');
    lead_email_html_expect(str_contains((string)$history[0]['body_html_safe'], '<table'), 'Existing outbound email did not receive the full HTML template.');
    lead_email_html_expect(str_contains((string)$history[0]['body_html_safe'], 'ES-Logo-Stack-500-x-150-px.png'), 'Existing outbound email template is missing the Elite Smiles logo.');
    lead_email_html_expect(!array_key_exists('tracking_token', $history[0]), 'Tracking token leaked into the communication payload.');
} finally {
    if ($testEmailId > 0) {
        db_query('DELETE FROM lead_emails WHERE id = :id LIMIT 1', ['id' => $testEmailId]);
    }
    if ($testLeadId > 0) {
        db_query('DELETE FROM leads WHERE id = :id LIMIT 1', ['id' => $testLeadId]);
    }
}

echo "Lead email HTML rendering tests passed.\n";
