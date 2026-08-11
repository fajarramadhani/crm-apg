# CRM Public Production Operations

## Environment

```env
FRONTEND_URL=https://crm.example.com
PUBLIC_TICKET_TRACKING_EXPIRY_DAYS=30
PUBLIC_TICKET_TRACKING_KEY=
PUBLIC_TICKET_TRACKING_KEYS=
PUBLIC_TICKET_TRACKING_KEY_VERSION=1
PUBLIC_HISTORY_DRIVER=mail
PUBLIC_HISTORY_OTP_EXPIRY_MINUTES=10
PUBLIC_HISTORY_MAX_ATTEMPTS=5
PUBLIC_HISTORY_RESEND_COOLDOWN_SECONDS=60
PUBLIC_HISTORY_ACCESS_TTL_MINUTES=15
PUBLIC_HISTORY_IDENTITY_KEY=
PUBLIC_HISTORY_OTP_PEPPER=
PUBLIC_ACTION_ACCESS_TTL_MINUTES=10
```

- Generate each secret as at least 32 random bytes, for example `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"`.
- Never commit generated values or print them in CI logs.
- Production requires the mail driver and rejects `log` or `array`, including unsafe nested failover/round-robin mailers.
- Configure a real SMTP/API mail transport and test OTP delivery before UAT.
- `FRONTEND_URL` must use HTTPS and point to the public frontend origin.
- Set a finite tracking expiry unless the approved business requirement explicitly requires non-expiring links.

## Tracking Key Rotation

Legacy single-key mode remains supported:

```env
PUBLIC_TICKET_TRACKING_KEY=base64:<current-key>
PUBLIC_TICKET_TRACKING_KEYS=
PUBLIC_TICKET_TRACKING_KEY_VERSION=1
```

Key-ring mode uses a JSON version-to-key map:

```env
PUBLIC_TICKET_TRACKING_KEY=
PUBLIC_TICKET_TRACKING_KEYS='{"1":"base64:<old-key>","2":"base64:<new-key>"}'
PUBLIC_TICKET_TRACKING_KEY_VERSION=2
```

Rotation procedure:

1. Deploy key-ring-capable code while retaining legacy single-key configuration.
2. Confirm every web, queue, and scheduler process runs the new code.
3. Add the old and new keys to `PUBLIC_TICKET_TRACKING_KEYS` and set the current version.
4. Rebuild Laravel configuration cache and restart long-running processes.
5. Verify old links, history link reconstruction, Supervisor copy-link, new issuance, and action credential flows.
6. Retain an old key until no active/unexpired token or idempotency replay requires its version.

Never store key material in the database. Removing an old key does not invalidate an already-held link because lookup is hash-based, but it prevents server-side reconstruction for the Supervisor panel, public history, and receipt replay.

## Scheduler and Queue

Run Laravel scheduler continuously:

```text
php artisan schedule:run
```

Scheduled cleanup commands:

```text
public-history:cleanup   hourly
public-actions:cleanup   hourly
idempotency:cleanup      daily
```

Commands are safe to retry and delete only expired credential/idempotency records. They do not delete ticket audit histories.

Keep the queue worker running for application notifications when the selected queue driver is asynchronous. Restart workers after deployments or configuration changes.

## Private Attachments

- `TICKET_ATTACHMENT_DISK` must use a non-public disk.
- Application startup is not a substitute for this runtime guard: every new ticket upload rejects the `public` disk or any disk configured with public visibility.
- Do not expose ticket storage through `storage:link`.
- Downloads must pass through authenticated, authorized controllers.
- Every ticket attachment uses an extensionless UUID storage name; the sanitized original name is display/download metadata only.
- MIME, extension, size, and count checks are not antivirus scanning. Add a malware-scanning pipeline before production if required by policy.

Inventory and migrate legacy public records before enabling public traffic:

```text
php artisan tickets:migrate-public-attachments --dry-run --limit=500
php artisan tickets:migrate-public-attachments --limit=500
```

- Use `--ticket=<id>` for a controlled canary.
- The command copies to the configured private disk, verifies SHA-256, writes a private rollback backup, updates locked metadata, and only then removes the public source.
- Re-running the command resumes from records whose `disk` remains `public`.
- Any failure leaves or restores metadata on `public` and removes incomplete private output.
- Rollback only records with a verified migration backup: `php artisan tickets:migrate-public-attachments --rollback --ticket=<id>`.
- Keep `attachment-migration-backups/public/*` until the migration acceptance window closes. Remove those backups only through an approved retention procedure after restore testing.
- Take a database and storage backup first. Record candidate/processed/failed counts and verify that no `ticket_attachments.disk = public` rows remain.

Create an isolated manual-UAT fixture only in a disposable local/test database:

```text
php artisan stage7:seed-public-uat --confirm=SEED-STAGE7-PUBLIC-UAT
```

The command refuses production/staging and database names that do not explicitly identify test, fixture, E2E, disposable, or Stage 7 data. It emits only synthetic identity data and a synthetic tracking URL.

## Tracking URL Redaction

Tracking tokens are bearer credentials in URL paths. Redact them before logs leave the edge.

Safe log examples:

```text
GET /track/[REDACTED]
GET /api/v1/public/tickets/track/[REDACTED]
```

Nginx mapping example:

```nginx
map $uri $safe_uri {
    ~^/track/[^/]+$ /track/[REDACTED];
    ~^/api/v1/public/tickets/track/[^/]+ /api/v1/public/tickets/track/[REDACTED];
    default $uri;
}

log_format crm_safe '$remote_addr - $request_method $safe_uri $status';
access_log /var/log/nginx/crm_access.log crm_safe;
```

Apply equivalent redaction to load balancers, CDN/WAF, APM traces, exception monitoring, browser analytics, session replay, and support diagnostics. Never capture Authorization or `X-Public-Action-Token` headers. Public tracking document responses should include:

```text
Referrer-Policy: no-referrer
Cache-Control: no-store, private
X-Robots-Tag: noindex, nofollow
```

The frontend also includes a static `no-referrer` meta tag as defense in depth.

## UAT Readiness Checklist

- Verify SMTP delivery, OTP expiry, attempts, cooldown, and generic errors.
- Verify old tracking links fail after rotate/revoke.
- Verify history and action credentials cannot cross tickets, branches, identities, or actions.
- Verify public responses contain no internal actors, notes, workflow metadata, keys, hashes, nonces, or credentials.
- Verify attachment upload/download authorization and private storage.
- Verify cleanup commands and scheduler health.
- Verify MySQL migrations, full tests, and concurrent mutation invariants in a dedicated test database.
- Verify reverse-proxy/APM log samples contain redacted paths before enabling public traffic.
