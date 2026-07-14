# Tic Hub

Tic Hub adalah aplikasi internal CRM dan IT service management APG. Repository ini berbentuk monorepo: React menyediakan antarmuka internal dan Laravel menyediakan REST API, autentikasi Sanctum, serta authorization berbasis role/permission. Data bisnis tiket masih berupa mock sampai fase integrasi berikutnya.

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

Phase 3 menyediakan Laravel Sanctum SPA authentication, delapan primary role, permission registry, middleware role/permission, login rate limit, seeder lokal, frontend auth bootstrap, protected route, role guard, real login/logout, dan session persistence. Backend tetap menjadi sumber kebenaran authorization.

Master data, division model, ticket CRUD/assignment, SLA, approval, workflow bisnis, dashboard API nyata, SSO, reset password, MFA, dan multi-role UI sengaja ditunda ke fase berikutnya.
