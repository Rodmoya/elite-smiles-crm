# Elite Smiles Codex Operator API Threat Model

## Scope and assumptions

This model covers the internet-facing Codex operator API in `app/api/codex/` and its handoff to `app/api/codex_control.php`. The CRM is assumed to remain a single-tenant PHP/MySQL application on BanaHosting, served only over HTTPS, with one trusted Codex service client initially. Public Meta, Twilio, website intake, kiosk, and patient viewer endpoints are out of scope except where Codex-triggered actions affect their shared lead data.

## Assets and trust boundaries

- Patient identity, contact information, conversation history, pipeline state, and appointment context in MySQL.
- Twilio, SMTP, Meta, OpenAI, Gemini, and Pushover credentials loaded from `.env`.
- The boundary from an internet client to the versioned API over HTTPS.
- The boundary from authenticated API actions to outbound SMS/email and CRM state changes.
- Audit, nonce, rate-limit, and idempotency records used to detect and constrain abuse.

## Principal abuse paths

| Priority | Threat | Likelihood / impact | Controls |
|---|---|---|---|
| Critical | Stolen API credential used to export leads or send patient messages | Medium / High | Header-only high-entropy token, hash at rest, HMAC request signing, short timestamp TTL, revocation, scoped permissions, audit trail |
| High | Captured request replayed to send duplicate SMS/email or repeat a stage change | Medium / High | Single-use nonce plus required idempotency key for every POST |
| High | Over-privileged client modifies stages or sends messages beyond its role | Medium / High | Per-client scopes and action-specific authorization, including conditional stage/write scopes on follow-up actions |
| High | Brute-force or automation causes data exposure or message spam | Medium / High | Constant-time token comparison by hash lookup, per-client rate limits, generic auth errors, HTTPS-only production access |
| Medium | Token leaks through URLs, referrers, access logs, or application errors | Medium / High | Query-string tokens removed, no-store/no-referrer headers, token prefix only in administration output, secrets excluded from audit metadata |
| Medium | Duplicate execution after a timeout causes inconsistent CRM state | Medium / Medium | Stored idempotency responses and request-hash binding |
| Medium | Compromised client hides activity or disputes an action | Low / High | Dedicated immutable-style API audit rows with request ID, client, action, lead, request hash, source IP, and outcome |
| Low | Expired nonce/idempotency records grow without bound | Medium / Low | Opportunistic retention cleanup and indexed expiry columns |

## Residual risks and deployment controls

- Application authentication does not replace host security. Add Cloudflare Access/WAF when the domain is available behind Cloudflare.
- The bearer token is also the request-signing secret. Protect the local `.secrets/codex-v1.json` file and rotate immediately if the workstation is compromised.
- Database administrators can alter audit rows. Export audit logs to an external append-only destination if non-repudiation becomes a requirement.
- Keep the legacy Codex endpoint disabled. Enabling `ELITE_CODEX_LEGACY_API_ENABLED` reintroduces static-token access without v1 replay and scope controls.

