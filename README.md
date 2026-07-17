# Tic Hub

Phase 8 implements `ready_for_development -> development_in_progress -> internal_testing -> ready_for_qa`, including PIC worklogs, progress snapshots, private development evidence, internal test cases/runs/results, failure-to-rework behavior, an IT Lead read-only queue, and requester-safe progress. QA execution remains outside this phase.

Phase 10 implements UAT execution, requester revision and sign-off: IT Lead requester assignment, ticket-specific scenarios, UAT runs/results, finding history, PIC rework gates, retest cycles, evidence authorization, and requester-driven `uat_approved`. See `docs/phase-10-uat-requester-signoff.md`.

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
php artisan migrate --seed
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

## Authentication lokal

Frontend mengambil CSRF cookie dari `/sanctum/csrf-cookie`, lalu memakai session cookie HttpOnly untuk `POST /api/v1/auth/login`, `GET /api/v1/auth/me`, dan `POST /api/v1/auth/logout`. Tidak ada bearer token yang disimpan di `localStorage`.

Seeder menyediakan delapan akun khusus environment `local`/`testing`: `requester`, `supervisor`, `itlead`, `pic`, `qa`, `manager`, `executive`, dan `admin`, masing-masing pada domain `@tichub.local` dengan password lokal `password`. `DevelopmentUserSeeder` menolak membuat akun tersebut di production. Credential ini hanya untuk development/testing dan tidak ditampilkan pada UI Login.

## Database

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

## Status implementasi

- Phase 3: Laravel Sanctum SPA authentication, primary roles, permission registry, login rate limit, local seeders, frontend auth bootstrap, protected routes, role guards, and session persistence.
- Phase 8: Development execution, worklogs, progress updates, and internal test runs.
- Phase 9: IT Lead QA assignment queue, QA dashboard & testing workspace, case/run/result execution, defect reporting, immutable defect history logs, PIC defect fixes, internal test validation gates, QA retests, and role-based data redaction.
- Phase 10: UAT assignment, requester execution/sign-off, rejected UAT rework, PIC finding lifecycle, retest cycles, evidence authorization, and UAT-specific responsive UI.
- Phase 11: Not started. Notifications and audit operations remain out of scope for the current delivery.
