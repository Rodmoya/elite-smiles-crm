# Codex Operator API

The dedicated operator endpoint is:

`POST https://hi.elitesmilesutah.com/crm/app/api/codex/v1/`

## Codex default route

When Rod asks Codex to check, update, send, move, audit, or summarize CRM
items, Codex should use this API first instead of scraping the browser UI.
Use the browser only when the task is visual, interactive, requires a logged-in
human page state, or the API does not yet expose the needed operation.

Preferred flow:

1. Use `crm_operator_command_center` first when Rod asks Codex to "check CRM",
   review daily/hourly status, or decide what to do next.
2. Use `pipeline_snapshot`, `lead_queue`, `list_leads`, `get_lead`, or
   `get_thread` for focused CRM reads.
3. Use draft actions before patient-facing messages unless Rod explicitly
   approves a send.
4. Use write actions such as `send_sms`, `send_email`, `move_stage`,
   `update_lead`, and `delete_lead` only with the API's required approval
   flags.
5. If a browser-only workflow is discovered, add the missing API route before
   making it a repeat process.

Only `health`, `capabilities`, and `stages` may use GET. All lead searches and operations use POST so patient information does not appear in access-log URLs.

## Authentication

Every request requires:

- `Authorization: Bearer <client token>`
- `X-Elite-Timestamp: <Unix timestamp>`
- `X-Elite-Nonce: <random single-use value>`
- `X-Elite-Signature: <hex HMAC-SHA256>`
- `Idempotency-Key: <stable unique value>` for POST requests

The signature payload is:

```text
timestamp\nnonce\nHTTP_METHOD\n/path?query\nsha256(raw_body)
```

The HMAC key is the client token. Requests expire after 60 seconds by default. The server stores only the SHA-256 token hash.

## Credential lifecycle

Create a 90-day credential and save it outside version control:

```powershell
php bin/codex-api-client.php create --label="Codex Desktop Operator" --output=".secrets\codex-v1.json"
```

List or revoke clients:

```powershell
php bin/codex-api-client.php list
php bin/codex-api-client.php revoke --id=2
```

The local signed-request helper reads `.secrets/codex-v1.json` by default:

```powershell
php bin/codex-api-request.php --action=health
php bin/codex-api-request.php --method=POST --action=get_lead --json='{"lead_id":123}' --idempotency-key=lead-123-read-001
```

On Windows PowerShell, prefer JSON files so quotes are not stripped before PHP
receives the request:

```powershell
'{"conversion_stage":"first_touch_sent","limit":10}' | Set-Content .secrets\lead-queue.json -Encoding ASCII
php bin/codex-api-request.php --credentials=.secrets/codex-v1-live.json --method=POST --action=lead_queue --json-file=.secrets\lead-queue.json --idempotency-key=lead-queue-first-touch-001
```

## Lead queues

Use `lead_queue` for Codex-friendly CRM pipeline reads. It returns the same
conversion-stage fields the Leads page uses, including `conversion_stage_key`,
`conversion_stage_label`, `next_action_key`, and `next_action_label`.

Common queues:

- `first_touch_sent`
- `active_follow_up`
- `no_answer_nurture`

## CRM operator brief

Use `crm_operator_command_center` for the daily/hourly Codex agent workflow.
It is read-only and returns `do_now`, `cleanup`, `manual_review`,
`nurture_candidates`, and `wait` buckets with draft messages and the reason each
lead appears. It does not send patient-facing messages or move stages.

```powershell
'{"mode":"hourly","limit":12}' | Set-Content .secrets\operator-brief.json -Encoding ASCII
php bin/codex-api-request.php --credentials=.secrets/codex-v1-live.json --method=POST --action=crm_operator_command_center --json-file=.secrets\operator-brief.json --idempotency-key=crm-operator-command-center-hourly-001
```

`crm_operator_brief` remains available as a compact legacy summary.

## Default scopes

The standard Codex operator has `system:read`, `leads:read`, `leads:write`, `messages:draft`, `messages:send`, `stages:write`, and `audit:read`. Administrative client management and duplicate merging are excluded by default.

Patient-facing sends still require `send_approved=true`. Stage changes still require `stage_approved=true`. Permanent deletes require `delete_approved=true`. Outbound communications create CRM messages, activities, and lead notes. Unread inbound messages remain unread unless `mark_inbound_reviewed=true` is explicitly supplied.

The legacy static-token endpoint is disabled unless `ELITE_CODEX_LEGACY_API_ENABLED=true` is deliberately configured.

On the first production v1 request only, if no active v1 client exists, the configured legacy secret can register itself as the initial scoped client. Prepare that signed migration credential locally with `migrate-legacy-credential`. Once any active v1 client exists, this bootstrap path is permanently unavailable unless every client is revoked or expired.
