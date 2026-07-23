# Tic Hub

Phase 15 implements the Knowledge Base and resolution-reuse vertical slice: permission-scoped article search and reading, draft/review/reject/publish/archive/restore lifecycle, immutable version snapshots, server-side sanitization, tag administration, ticket links and redacted ticket-derived drafts, deterministic recommendations, feedback, activity audit, and deduplicated in-app workflow notifications. The responsive frontend includes reader, author, reviewer, tag-management, and ticket-resolution workflows. See `docs/phase-15-knowledge-base-resolution-reuse.md`.

Phase 8 implements `ready_for_development -> development_in_progress -> internal_testing -> ready_for_qa`, including PIC worklogs, progress snapshots, private development evidence, internal test cases/runs/results, failure-to-rework behavior, an IT Lead read-only queue, and requester-safe progress. QA execution remains outside this phase.

Phase 10 implements UAT execution, requester revision and sign-off: IT Lead requester assignment, ticket-specific scenarios, UAT runs/results, finding history, PIC rework gates, retest cycles, evidence authorization, and requester-driven `uat_approved`. See `docs/phase-10-uat-requester-signoff.md`.

Phase 11 implements two-step release approval, versioned release/rollback preparation, checklist gates, internal evidence, and `release_ready` without deployment execution. See `docs/phase-11-approval-release-preparation.md`.

Phase 7 extends the production workflow from assignment through PIC analysis/RCA, versioned solution planning, IT Lead revision or approval, and `ready_for_development`. The PIC workspace and IT Lead Plan Review queue now use the Laravel API, preserve prior plan versions, enforce optimistic locking and ownership, and keep requester history free of internal technical detail. See `docs/phase-7-analysis-solution-planning.md`.

Phase 6 introduced IT Lead triage, final priority, calendar-aware SLA initialization, transactional primary PIC assignment, and the initial PIC workspace. Requester and Supervisor Phase 5 behavior remains intact. See `docs/phase-6-triage-sla-assignment.md`.

Phase 4 adds the production master-data foundation used by future ticket forms and workflow: divisions, branches, applications/modules, ticket categories/priorities, SLA policies, working calendars, holidays, and basic user organizational assignments. The Laravel API exposes active read-only data to authenticated roles and paginated management endpoints to Admin. See `docs/phase-4-master-data-foundation.md` for setup, endpoint, validation, and verification details.

Tic Hub adalah aplikasi internal CRM dan IT service management APG. Repository ini berbentuk monorepo: React menyediakan antarmuka internal dan Laravel menyediakan REST API, autentikasi Sanctum, serta authorization berbasis role/permission. Alur Requester ke validasi Supervisor sudah memakai API dan database nyata; modul fase berikutnya masih dapat memakai mock secara bertahap.

## Struktur repository

```text
apg-crm/
├── frontend/   # React, TypeScript, Vite, dan Tailwind CSS
├── backend/    # Laravel REST API dan koneksi MySQL
├── docs/       # audit, desain, kontrak, roadmap, dan catatan fase
├── AGENTS.md
└── README.md
```

## Requirement

- Node.js 20 atau lebih baru
- Corepack dan pnpm 11
- PHP 8.3 atau lebih baru dengan PDO MySQL dan PDO SQLite
- Composer 2
- MySQL 8 atau lebih baru untuk development

## Menyiapkan environment

Frontend:

```bash
cd frontend
cp .env.example .env.local
corepack pnpm install --frozen-lockfile
```

Backend:

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

Sesuaikan kredensial MySQL hanya di `backend/.env`. File `.env` dan credential tidak boleh dicommit. Buat database development bernama `apg_crm`, atau ubah `DB_DATABASE` sesuai environment lokal.

## Menjalankan aplikasi

Frontend tersedia secara default di `http://localhost:5173`:

```bash
cd frontend
corepack pnpm dev
```

Backend tersedia di `http://localhost:8000`:

```bash
cd backend
php artisan serve
```

Health check API tersedia di `GET http://localhost:8000/api/v1/health`. Frontend dan backend harus memakai hostname yang konsisten (`localhost` pada contoh) agar cookie first-party Sanctum bekerja.

## Authentication dan provisioning

Frontend mengambil CSRF cookie dari `/sanctum/csrf-cookie`, lalu memakai session cookie HttpOnly untuk `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, dan `POST /api/v1/auth/logout`. Tidak ada bearer token yang disimpan di `localStorage`.

Repository tidak menyediakan akun bersama atau password default. Default seeding hanya membuat delapan role otorisasi. Untuk environment testing/staging, provision satu identitas bernama menggunakan password dari environment terlindungi melalui `php artisan users:provision-testing`; lihat `docs/testing-environment-setup.md`. Command tersebut menolak production dan tidak pernah menimpa user yang sudah ada.

## Database

The repository currently contains **22 migrations**. The Phase 15 migration creates eight Knowledge Base tables for annual numbering, articles, immutable versions, tags, article-tag membership, ticket links, per-user feedback, and activity logs; see `docs/database-design.md`.

Jalankan migration development setelah memastikan `.env` menunjuk ke database yang benar:

```bash
cd backend
php artisan migrate
php artisan migrate:status
```

`migrate:fresh` menghapus seluruh tabel. Gunakan hanya pada database disposable/test setelah memeriksa `APP_ENV` dan nama database:

```bash
php artisan migrate:fresh
```

Automated test memakai SQLite in-memory melalui `phpunit.xml`, sehingga tidak menyentuh database MySQL development.

## Verifikasi

Frontend:

```bash
cd frontend
corepack pnpm install --frozen-lockfile
corepack pnpm typecheck
corepack pnpm format:check
corepack pnpm build
```

Backend:

```bash
cd backend
composer install
php artisan route:list
php artisan test
vendor/bin/pint --test
```

CI mempertahankan suite SQLite untuk feedback cepat dan menjalankan suite backend yang sama pada service MySQL 8.4 disposable. Job MySQL juga memverifikasi `migrate:fresh --seed`, rollback migration terakhir, reapply, dan migration status; hasilnya baru menjadi release evidence setelah workflow GitHub Actions terkait lulus.

Current Phase 15 verification baseline:

- Backend automated suite: **182 tests, 1,145 assertions**, all passing.
- API inventory: **25 Knowledge Base routes** and **268 API routes total**.
- Frontend: TypeScript check, Prettier check, and production build pass.
- Frontend routes are lazy-loaded. The entry chunk is 288.88 kB (90.05 kB gzip) and the largest generated chunk is 303.42 kB (90.49 kB gzip), both below Vite's 500 kB warning threshold.
- HTTP server E2E passed 12 Knowledge Base lifecycle, authorization, sanitization, feedback, audit, and request-ID checks against a disposable SQLite runtime.
- Chrome browser verification passed for desktop PIC (1440x900), mobile Requester (390x844), and desktop Admin (1440x900), including login/logout, Knowledge Base navigation, role-specific management, console/page errors, HTTP 500 responses, and horizontal overflow.

Current testing-readiness follow-up:

- Runtime frontend fixture datasets and simulated Admin/Executive/SLA pages have been removed; legacy URLs redirect to API-backed queues, reports, or master-data pages.
- Default database seeding creates canonical roles only and is covered by a regression test that asserts zero users and zero organization master records.
- Named non-production users are provisioned explicitly with protected credentials; no shared account or default password is shipped.
- Backend validation passes **187 tests / 1,156 assertions**. Frontend typecheck, formatting, and production build pass with a 277.49 kB entry chunk (86.31 kB gzip).

## Status implementasi

- Phase 3: Laravel Sanctum SPA authentication, primary roles, permission registry, login rate limit, local seeders, frontend auth bootstrap, protected routes, role guards, and session persistence.
- Phase 8: Development execution, worklogs, progress updates, and internal test runs.
- Phase 9: IT Lead QA assignment queue, QA dashboard & testing workspace, case/run/result execution, defect reporting, immutable defect history logs, PIC defect fixes, internal test validation gates, QA retests, and role-based data redaction.
- Phase 10: UAT assignment, requester execution/sign-off, rejected UAT rework, PIC finding lifecycle, retest cycles, evidence authorization, and UAT-specific responsive UI.
- Phase 11: Business approval, technical readiness, release and rollback plans, release checklist, readiness validation, and `release_ready`.
- Phase 14: Notification center, SLA escalation, and operational alerts. In-app notifications, delivery logs, preferences authorization, SLA warning/breach scanning, inactivity tracking, role-specific alert APIs, and fully responsive layouts.
- Phase 15: Knowledge Base article lifecycle, visibility and permissions, version history, sanitization/redaction, review separation of duties, publishing, ticket resolution reuse, deterministic recommendations, feedback, audit, notifications, and responsive frontend workflows.

Phase 16 is explicitly out of scope for the completed Phase 15 delivery.

## Operations and release readiness

Phase 16 hardening and staging-readiness materials are indexed in [`docs/README.md`](docs/README.md): architecture, API, role matrix, database, scheduler/queue, backup/restore, deployment, rollback, logging/monitoring, release checklist, final UAT, handover, and known risks. Production deployment and human sign-off are not performed by this repository workflow.
