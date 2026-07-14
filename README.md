# Tic Hub

Tic Hub adalah aplikasi internal CRM dan IT service management APG. Repository ini berbentuk monorepo: prototype React tetap berjalan dengan data mock, sedangkan Laravel menyediakan fondasi REST API yang akan dikembangkan bertahap.

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

Health check API tersedia di `GET http://localhost:8000/api/v1/health`.

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

Phase 2 menyediakan struktur monorepo, Laravel API v1, health check database, response JSON standar, request ID, exception handling, CORS, konfigurasi MySQL, serta test fondasi. Frontend belum terintegrasi ke API dan tetap memakai repository/data mock.

Authentication, Laravel Sanctum, login/logout backend, RBAC, master data, ticket CRUD, SLA, approval, workflow bisnis, dashboard API, dan fitur operasional lain sengaja belum tersedia. Pekerjaan tersebut dimulai pada fase berikutnya tanpa mengubah desain frontend secara besar-besaran.
