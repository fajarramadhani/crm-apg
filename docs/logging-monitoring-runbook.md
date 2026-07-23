# Logging and Monitoring Runbook

## Logging policy

Application error logs and immutable business activity records serve different purposes and must not be merged. Production application logs should use stdout/stderr or an approved centralized destination with retention and access controls. Rotate local fallback logs daily and retain them according to the approved security policy.

Structured context should include request ID, environment, release commit, actor ID when authenticated, safe target ticket/article ID, event type, route, outcome, and a stable failure code. Never log passwords, authorization headers, session or CSRF cookies, tokens, `.env`, private keys, database DSNs, full sensitive request bodies, evidence contents, or unredacted ticket/Knowledge Base text.

## Minimum monitors

- Public liveness `/up`.
- Deployment readiness `/api/health` with expected 200/503 behavior.
- HTTP 5xx rate and p95 latency.
- Authentication throttling and unusual 401/403 volume.
- Database connectivity and saturation.
- Scheduler heartbeat, command duration, and failures.
- Queue depth/oldest job/failed jobs if queued delivery is enabled.
- Private storage errors and capacity.
- Backup age and latest restore-drill status.

## Incident response

Correlate reports using `X-Request-ID`. Preserve relevant logs read-only, classify severity, notify the Incident Commander `[TBD]`, and follow rollback criteria. Do not expose internal exception messages to users. Phase 16 adds safe security headers and removes database exception text from health-check logs.

No paid monitoring provider was selected in Phase 16. Infrastructure Owner must map these signals to the approved platform before staging approval.
