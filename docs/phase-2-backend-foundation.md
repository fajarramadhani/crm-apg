# Phase 2 — Laravel Backend Foundation and MySQL Environment

## Scope and outcome

Phase 2 converts the repository into a frontend/backend monorepo and establishes a tested Laravel REST API foundation. It does not implement authentication, Sanctum, RBAC, master data, ticket CRUD, SLA, approvals, workflow, or frontend API integration.

The implementation brief for this phase supersedes the older phase numbering in `implementation-roadmap.md`; business work remains deferred according to that roadmap.

## Repository structure

Before:

```text
apg-crm/
├── src/
├── package.json
├── pnpm-lock.yaml
├── docs/
└── AGENTS.md
```

After:

```text
apg-crm/
├── frontend/
│   ├── src/
│   ├── package.json
│   ├── pnpm-lock.yaml
│   └── vite.config.ts
├── backend/
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── tests/
│   ├── artisan
│   └── composer.json
├── docs/
├── AGENTS.md
└── README.md
```

The existing frontend was moved without rewriting components, routes, dummy repositories, or visual design. `VITE_API_BASE_URL` now points to `/api/v1`, but no page calls the backend yet.

## Runtime versions

- PHP 8.3.30
- Composer 2.9.4
- Laravel Framework 13.19.0
- PHPUnit 12.5.31
- Laravel Pint 1.29.3
- Node.js 24.18.0
- pnpm 11.13.0

Laravel 13 was selected by Composer as the current stable release compatible with the local PHP 8.3 runtime. No production package was added beyond the standard Laravel scaffold.

## Architecture decisions

- The backend is a modular Laravel application exposed as JSON REST API routes under `/api/v1`.
- MySQL is the development database configured through `backend/.env`; credentials are never committed.
- PHPUnit uses SQLite `:memory:` from `phpunit.xml`. Foundation tests therefore cannot erase or mutate the development database.
- The default Laravel user, cache, and job migrations are retained. No CRM business schema or seed data is introduced.
- Application time defaults to `Asia/Jakarta`; health timestamps are generated at runtime in ISO-8601 format.
- The default Blade welcome file remains part of the Laravel scaffold but is unrelated to the API.

## Endpoint

`GET /api/v1/health` checks whether the configured database can establish a PDO connection.

Healthy response:

```json
{
  "success": true,
  "message": "Tic Hub API is healthy",
  "data": {
    "status": "ok",
    "database": "connected"
  },
  "meta": {
    "timestamp": "2026-07-14T17:07:40+07:00",
    "request_id": "123e4567-e89b-12d3-a456-426614174000"
  }
}
```

A database failure returns HTTP 503, `DATABASE_UNAVAILABLE`, a timestamp, and request ID without returning a query, credential, path, or stack trace. Technical details remain in the server log.

## Standard response and exception handling

`App\Support\ApiResponse` builds reusable success, validation, and general error envelopes. Central exception configuration handles:

- route not found as JSON 404 / `NOT_FOUND`;
- method not allowed as JSON 405 / `METHOD_NOT_ALLOWED`;
- validation errors as JSON 422;
- database query errors as safe JSON 503 / `DATABASE_ERROR`;
- unhandled errors as safe JSON 500 / `SERVER_ERROR`.

API exception responses remain safe even when local debug mode is enabled. Laravel still reports technical exceptions to server logs.

## Request ID and logging

`AssignRequestId` is prepended globally. It accepts `X-Request-ID` only when the value is a UUID no longer than 64 characters; otherwise it creates a UUID. The middleware stores the value on the request, adds it to Laravel logging context, exposes it in `meta.request_id`, and returns it in the `X-Request-ID` response header.

## CORS

`config/cors.php` reads one or more comma-separated origins from `FRONTEND_URL`. The default is `http://localhost:5173`. API methods and headers are allowed, `X-Request-ID` is exposed, and credentials support is enabled for a future Sanctum phase. Wildcard production origins are not configured.

## Database setup

Development uses MySQL through the following environment keys:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apg_crm
DB_USERNAME=root
DB_PASSWORD=
```

Normal commands:

```bash
php artisan migrate
php artisan migrate:status
```

`php artisan migrate:fresh` must only be used after confirming a disposable/test environment. Phase 2 verification ran it against an ignored SQLite test file with `APP_ENV=testing`, then removed that file.

## Automated tests

`tests/Feature/ApiFoundationTest.php` contains 10 endpoint-level tests with 60 assertions:

1. healthy database and standard health envelope;
2. valid client request ID preservation;
3. generated UUID when the header is missing;
4. invalid/overlong request ID replacement;
5. standard JSON API 404;
6. standard JSON 405;
7. validation error envelope;
8. safe generic server error while debug is enabled;
9. configured CORS origin and credentials;
10. safe health response when the database is unavailable.

## Verification results

Frontend:

- `corepack pnpm install --frozen-lockfile`: passed.
- `corepack pnpm typecheck`: passed.
- `corepack pnpm format:check`: passed.
- `corepack pnpm build`: passed; the existing approximately 800 kB chunk warning remains non-blocking.
- Agent-browser desktop 1440×900: Login, Ticket History, and Create Ticket rendered without document overflow.
- Agent-browser mobile 390×844: Login and User Dashboard rendered without document overflow or application console errors.

Backend:

- `composer install`: passed and a second run reported nothing to install/update/remove.
- `php artisan key:generate`: passed using a temporary ignored `.env`, which was deleted afterward.
- `php artisan migrate:fresh --force`: passed on disposable SQLite; three default migrations ran.
- `php artisan migrate:status`: all three default migrations reported `Ran`.
- `php artisan route:list --path=api`: one route, `GET|HEAD api/v1/health`.
- `php artisan test`: 10 passed, 60 assertions.
- `vendor/bin/pint --test`: passed.
- PHP syntax lint: 31 application/config/migration/route/test files passed.
- Live HTTP check: health 200, JSON 404, JSON 405, CORS headers, and request ID passed on temporary port 8010. Port 8000 was already occupied by an unrelated local PHP service and was not stopped.

## Commands used

The main verification commands were:

```bash
cd frontend
corepack pnpm install --frozen-lockfile
corepack pnpm typecheck
corepack pnpm format:check
corepack pnpm build
corepack pnpm dev --host 127.0.0.1 --port 5173
```

```bash
cd backend
composer install
php artisan key:generate
php artisan migrate:fresh --force
php artisan migrate:status
php artisan route:list --path=api
php artisan test
vendor/bin/pint --test
php artisan serve
```

## Deferred work and Phase 3 risks

Deliberately deferred: Sanctum, login/logout, password handling, RBAC, user/role/permission management, all CRM master tables, ticket/workflow/SLA/approval APIs, attachments, notifications, Knowledge Base, dashboard APIs, full frontend integration, Docker, CI/CD, and production deployment.

Risks for Phase 3:

- authentication topology and Sanctum domain/cookie settings still need a decision;
- role scope and executive redaction rules require policy tests before exposing business data;
- MySQL credentials/database availability vary by developer machine and were not committed;
- workflow, SLA calendar, and schema decisions must be finalized before business migrations;
- the existing frontend bundle-size warning should be addressed separately without mixing it into authentication work.
