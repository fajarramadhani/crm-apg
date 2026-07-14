# Phase 3 — Authentication and Role-Based Access Control

## Scope and decisions

Phase 3 mengganti simulasi login/logout dan role switcher dengan Laravel Sanctum SPA session. Backend adalah authority untuk identity dan permission; frontend hanya memakai response backend untuk route/rendering guard. Setiap user memiliki satu primary role melalui `users.role_id`. Relasi dan permission service dipisahkan agar implementasi multi-role kelak tidak mengubah kontrak `role`/`permissions` pada frontend.

Supervisor diasumsikan satu divisi, tetapi `division_id` ditunda sampai tabel/model division dibuat pada fase master data. Executive hanya mendapat aggregate permission dan ditolak dari detail teknis. SSO, reset password, MFA, dan email verification tidak termasuk fase ini.

## Sanctum session and CSRF

- Dependency: `laravel/sanctum` 4.x, diperlukan untuk first-party SPA cookie authentication.
- Frontend memanggil `GET /sanctum/csrf-cookie` sebelum login.
- Semua request memakai `credentials: include`; mutation meneruskan cookie `XSRF-TOKEN` sebagai header `X-XSRF-TOKEN`.
- Session disimpan dalam cookie HttpOnly dan tidak ada token di `localStorage`.
- Login meregenerasi session; logout menginvalidasi session dan meregenerasi CSRF token.
- CORS hanya mengizinkan origin dari `FRONTEND_URL`, termasuk credentials dan path Sanctum CSRF.
- `SANCTUM_STATEFUL_DOMAINS`, cookie secure, domain, dan SameSite dikendalikan environment.

## Database and seed data

`roles` menyimpan `key`, display `name`, description opsional, `is_active`, dan timestamps. `users` mendapat nullable foreign key `role_id` untuk migrasi aman dari data lama, `is_active`, dan `last_login_at`. Login menolak user tanpa role aktif sehingga account tanpa role tidak dapat memperoleh session aplikasi.

Role canonical: `requester`, `supervisor`, `it_lead`, `pic`, `qa`, `manager`, `executive`, dan `admin`.

`RoleSeeder` aman dijalankan di semua environment. `DevelopmentUserSeeder` hanya berjalan pada `local`/`testing`, membuat akun `<role>@tichub.local` (IT Lead: `itlead@tichub.local`) dengan password development `password` yang di-hash. Seeder tidak membuat akun development pada production.

## Backend authorization

`config/permissions.php` memetakan role ke permission. `PermissionRegistry`, `User::hasRole()`, dan `User::hasPermission()` menyediakan API reusable. Middleware alias `active`, `role`, dan `permission` memusatkan enforcement. User atau role yang dinonaktifkan kehilangan akses session pada request terlindungi.

Endpoint:

| Method | Endpoint | Protection |
| --- | --- | --- |
| GET | `/sanctum/csrf-cookie` | web + CSRF bootstrap |
| POST | `/api/v1/auth/login` | guest, throttle 5/email+IP/minute |
| GET | `/api/v1/auth/me` | `auth:sanctum`, active user/role |
| POST | `/api/v1/auth/logout` | `auth:sanctum` |

Endpoint `/api/v1/protected/*` hanya didaftarkan pada environment `local`/`testing` sebagai verification fixture untuk Admin, Executive aggregate, dan technical-detail permission. Endpoint tersebut tidak tersedia pada production.

## Frontend authentication and guards

Layer frontend terdiri dari API client, auth repository, auth service, `AuthProvider`, session initialization via `/auth/me`, protected shell, role guard, dan Unauthorized page. Refresh browser memulihkan user dari session backend. Error login memakai pesan API yang aman, tombol submit disabled/loading, dan field tidak berisi credential contoh.

Redirect role:

| Role | Route |
| --- | --- |
| Requester | `/user/dashboard` |
| Supervisor | `/supervisor/dashboard` |
| IT Lead | `/itlead/dashboard` |
| PIC | `/pic/dashboard` |
| QA | `/qa/dashboard` |
| Manager | `/manager/approval` (route existing) |
| Executive | `/executive/dashboard` |
| Admin | `/admin/console` |

Role switcher interaktif dihapus. Pada development hanya ada label informational untuk role session saat ini; build production tidak menampilkannya dan label tersebut tidak dapat mengubah session atau authorization.

## Tests and verification

Backend feature tests mencakup login success/failure, inactive user/role, redaction password, validation envelope, `/auth/me`, logout/session, standardized 401/403, Admin access, Executive aggregate/technical denial, technical-role access, rate limit, dan request ID. SQLite in-memory pada `phpunit.xml` menjaga database development tidak tersentuh.

Hasil command, live HTTP, browser desktop/mobile, dan commit dicatat pada laporan akhir task. Bundle warning frontend lama tetap non-blocking dan tidak dicampur dengan scope authentication.

## Deferred to Phase 4

Division/master data, admin user CRUD, ticket CRUD/assignment, workflow, SLA, QA/UAT/approval/deployment, notification bisnis, attachment, Knowledge Base, dashboard API, SSO, reset password, MFA, multi-role UI, serta executive aggregate data endpoint tetap ditunda.
