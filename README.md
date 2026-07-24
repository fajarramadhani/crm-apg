# Tic Hub

Tic Hub adalah aplikasi internal CRM dan IT service management APG yang mengelola ticket, knowledge base, dan workflow operasional. Aplikasi ini menyediakan antarmuka untuk Requester, Supervisor, IT Lead, PIC, QA, dan Admin dengan permission-scoped features dan audit trails lengkap.

## Fitur

- **Ticketing System**: Permintaan, triage, SLA management, dan tracking end-to-end
- **Development Workflow**: Assignment, worklog, progress tracking, dan internal testing
- **QA Execution**: Test case management, defect reporting, dan retest cycles
- **UAT Management**: Requester execution, sign-off, dan evidence authorization
- **Release Management**: Release approval, checklist validation, dan rollback preparation
- **Knowledge Base**: Article lifecycle, versioning, sanitization, dan deterministic recommendations
- **Notification Center**: SLA escalation, operational alerts, dan in-app notifications
- **Multi-Role Authorization**: Role-based permission, data redaction, dan audit logging

## Teknologi

- **Frontend**: React, TypeScript, Vite, Tailwind CSS, TanStack Query, dan React Router
- **Backend**: Laravel 11, Sanctum, Laravel Query Builder, dan email notifications
- **Database**: MySQL 8, migrations, seeders, dan transaction handling
- **Testing**: PHPUnit dengan SQLite in-memory dan frontend type checking

## Persyaratan

- Node.js 20+
- Corepack dan pnpm 11
- PHP 8.3+
- Composer 2
- MySQL 8+
- Git

## Instalasi

### Clone Repository

```bash
git clone https://github.com/APGCore/crm-apg.git
cd crm-apg
```

### Setup Frontend

```bash
cd frontend
cp .env.example .env.local
corepack pnpm install --frozen-lockfile
```

### Setup Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
php artisan db:seed --class=RoleSeeder
```

**Catatan**: Sesuaikan kredensial MySQL di `backend/.env`. Database development bernama `apg_crm` atau ubah `DB_DATABASE` sesuai environment lokal.

## Environment Variables

### Frontend (.env.local)

```env
VITE_API_BASE_URL=http://localhost:8000
```

### Backend (.env)

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=apg_crm
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
```

**Jangan memasukkan API key, password, atau token asli ke repository.**

## Struktur Folder

```text
apg-crm/
├── frontend/              # React, TypeScript, Vite, Tailwind CSS
│   ├── src/
│   │   ├── app/          # Routing dan layout
│   │   ├── components/   # Reusable UI components
│   │   ├── pages/        # Page components
│   │   ├── hooks/        # Custom React hooks
│   │   ├── services/     # API services
│   │   ├── types/        # TypeScript types
│   │   └── utils/        # Utility functions
│   └── public/
│
├── backend/              # Laravel REST API
│   ├── app/
│   │   ├── Http/        # Controllers, Requests, Resources
│   │   ├── Models/      # Eloquent models
│   │   ├── Services/    # Business logic
│   │   └── Rules/       # Validation rules
│   ├── database/
│   │   ├── migrations/  # Database migrations
│   │   └── seeders/     # Database seeders
│   ├── routes/          # API routes
│   └── tests/           # PHPUnit tests
│
├── docs/                # Documentation, roadmap, dan design docs
├── AGENTS.md           # Agent instructions
└── README.md

## Perintah

### Frontend

Frontend tersedia di `http://localhost:5173`:

```bash
cd frontend
corepack pnpm dev
```

### Backend

Backend API tersedia di `http://localhost:8000`:

```bash
cd backend
php artisan serve
```

Health check API: `GET http://localhost:8000/api/v1/health`

**Catatan**: Frontend dan backend harus memakai hostname konsisten agar cookie first-party Sanctum bekerja.

### Menjalankan Verifikasi

**Frontend:**

```bash
cd frontend
corepack pnpm install --frozen-lockfile
corepack pnpm typecheck
corepack pnpm format:check
corepack pnpm build
```

**Backend:**

```bash
cd backend
composer install
php artisan route:list
php artisan test
vendor/bin/pint --test
```

## Authentication

Frontend menggunakan **Laravel Sanctum** dengan:
- CSRF cookie dari `/sanctum/csrf-cookie`
- Session cookie HttpOnly untuk login/logout
- Tidak ada bearer token di `localStorage`
- Role-based access control (RBAC) untuk setiap endpoint

## Provisioning User Testing

Repository tidak menyediakan akun bersama atau password default. Untuk environment testing/staging:

```bash
php artisan users:provision-testing
```

Command ini menolak production dan tidak pernah menimpa user yang sudah ada. Lihat `docs/testing-environment-setup.md` untuk detail.

## Database

Repository berisi **22 migrations** untuk seluruh fase implementasi:

- **Phase 4**: Master data (divisions, branches, applications, categories, SLA)
- **Phase 3-15**: Ticket workflow, knowledge base, notifications

Jalankan migration:

```bash
cd backend
php artisan migrate
php artisan migrate:status
```

Untuk fresh database (hanya di development):

```bash
php artisan migrate:fresh --seed
```

**Catatan**: Testing menggunakan SQLite in-memory; tidak menyentuh database MySQL development.

## Deployment

- **Development**: `http://localhost:5173` (Frontend) dan `http://localhost:8000` (Backend)
- **Production**: TBD
- **Branch Production**: `main`
- **Branch Development**: `development`

## Status Proyek

Proyek sedang dalam tahap pengembangan dengan 15 phase yang sudah diimplementasikan:

- **Phase 3**: Autentikasi Sanctum, roles, dan permission registry
- **Phase 4**: Master data foundation (divisions, branches, applications, SLA)
- **Phase 6**: IT Lead triage dan SLA initialization
- **Phase 7**: Analysis, solution planning, dan versioned plans
- **Phase 8**: Development execution dan internal testing
- **Phase 9**: QA execution dan defect management
- **Phase 10**: UAT execution dan requester sign-off
- **Phase 11**: Release approval dan preparation
- **Phase 14**: Notification center dan SLA escalation
- **Phase 15**: Knowledge Base dan resolution reuse

Rincian lengkap: Lihat [`docs/README.md`](docs/README.md)

## Kontributor

- APG Development Team — CRM & IT Service Management
- Architecture & Backend — Laravel API & Database Design
- Frontend & UI — React & Responsive Design

## Lisensi

Repository ini bersifat internal dan tidak boleh disebarluaskan tanpa izin dari APG Core.
