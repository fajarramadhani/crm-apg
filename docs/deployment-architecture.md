# Deployment Architecture

## Target topology

- Frontend: immutable Vite static assets behind HTTPS with SPA fallback to `index.html`.
- Backend: Laravel on a conventional PHP runtime behind a reverse proxy, document root `backend/public`.
- Database: managed or supervised MySQL 8+ with encrypted transport and backups.
- Private files: durable private object/file storage, never a public permanent URL.
- Scheduler: one supervised Laravel scheduler invocation per minute.
- Queue: database-backed capability; worker required only after queued jobs are explicitly enabled.
- Logs: centralized or platform-collected structured application logs plus separate business audit tables.
- Monitoring: liveness, readiness, latency/errors, scheduler, storage, database, and backups.

Staging and production use separate databases, storage namespaces, secrets, and domains. Domain names are environment variables and are not hard-coded. Prefer same-site HTTPS frontend/API domains so Sanctum cookies can use secure, HttpOnly, SameSite=Lax settings. CORS must list exact frontend origins and never combine wildcard origin with credentials.

## Build and runtime

Use the approved Node/pnpm versions to build `frontend/dist`; Node is not required to serve static assets. Install backend dependencies from `composer.lock` with PHP 8.3+ and required PDO extensions. The reverse proxy must set trusted HTTPS forwarding correctly, limit request body size, deny access outside `public`, and apply HSTS only after HTTPS validation.

## Storage and rollback

`storage` and cache paths require least-privilege write permission. Production evidence must use durable private storage rather than an ephemeral release directory. Frontend/backend artifacts are versioned by commit; rollback selects the previously approved immutable artifact. Database rollback requires separate approval and may use forward-fix or restore as documented in the rollback runbook.
