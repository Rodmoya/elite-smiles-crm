# Codex Operator API

The dedicated operator endpoint is:

`POST https://hi.elitesmilesutah.com/crm/app/api/codex/v1/`

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

## Default scopes

The standard Codex operator has `system:read`, `leads:read`, `leads:write`, `messages:draft`, `messages:send`, `stages:write`, and `audit:read`. Administrative client management and duplicate merging are excluded by default.

Patient-facing sends still require `send_approved=true`. Stage changes still require `stage_approved=true`. Outbound communications create CRM messages, activities, and lead notes. Unread inbound messages remain unread unless `mark_inbound_reviewed=true` is explicitly supplied.

The legacy static-token endpoint is disabled unless `ELITE_CODEX_LEGACY_API_ENABLED=true` is deliberately configured.

On the first production v1 request only, if no active v1 client exists, the configured legacy secret can register itself as the initial scoped client. Prepare that signed migration credential locally with `migrate-legacy-credential`. Once any active v1 client exists, this bootstrap path is permanently unavailable unless every client is revoked or expired.
