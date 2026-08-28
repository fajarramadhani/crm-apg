# Laporan Perbaikan Login & Sesi SPA (Laravel Sanctum + React Vite)

- **Tanggal**: 28 Agustus 2026
- **Status**: Selesai / Terverifikasi Penuh

## Ringkasan Masalah

Pengguna mengalami kendala tidak bisa masuk ke dashboard Tic Hub saat login melalui antarmuka web (`http://localhost:5173/login`), dengan gejala:
1. Form login selalu menampilkan alert *"Sesi Anda telah berakhir. Silakan masuk kembali."*
2. API login (`POST /api/v1/auth/login`) sukses mengembalikan status 200 dan data pengguna.
3. Namun tepat setelah login berhasil dan dialihkan ke dashboard, semua panggilan API `GET` berikutnya (seperti `GET /api/v1/auth/me`, `GET /api/v1/notifications/unread-count`, dan `GET /api/v1/admin/users`) langsung mengembalikan status **401 Unauthenticated**.
4. Akibat 401 tersebut, frontend memicu event `SESSION_EXPIRED_EVENT`, menghapus sesi pengguna di state React, dan mengarahkan kembali ke `/login`.

## Akar Masalah (Root Cause Analysis)

1. **Meta Referrer Policy di Frontend (`frontend/index.html`)**:
   - `index.html` memiliki tag: `<meta name="referrer" content="no-referrer" />`.
   - Hal ini membuat browser **tidak pernah mengirimkan header `Referer`** pada request apa pun.
   - Pada request `POST` (`/api/v1/auth/login`), browser secara otomatis menyertakan header `Origin: http://localhost:5173`.
   - Namun pada request `GET` (`/api/v1/auth/me`, `/notifications/unread-count`, dll), browser **tidak mengirimkan header `Origin`**, dan karena `no-referrer`, browser juga **tidak mengirimkan header `Referer`**.
   - Laravel Sanctum middleware (`EnsureFrontendRequestsAreStateful::fromFrontend`) mengandalkan header `referer` atau `origin` untuk menentukan apakah request berasal dari SPA first-party:
     ```php
     $domain = $request->headers->get('referer') ?: $request->headers->get('origin');
     if (is_null($domain)) return false;
     ```
   - Karena kedua header bernilai `null`, Sanctum menilai request sebagai *non-frontend*, sehingga **middleware session Laravel dilewati (skipped)** dan cookie sesi `apg_crm_session` diabaikan, menyebabkan 401 Unauthenticated.

2. **Konfigurasi Proxy Vite (`frontend/vite.config.ts`)**:
   - Proxy Vite meneruskan header CORS backend (`access-control-allow-*`) pada respons same-origin, serta membutuhkan `cookieDomainRewrite` dan `changeOrigin: true` agar cookie terikat dengan benar ke origin proxy frontend `localhost:5173`.

## Perubahan yang Dilakukan

1. **`frontend/index.html`**:
   - Mengubah `<meta name="referrer" content="no-referrer" />` menjadi `<meta name="referrer" content="strict-origin-when-cross-origin" />`.
   - Hal ini memastikan browser mengirimkan origin pada header `Referer` untuk request same-origin & cross-origin aman, sehingga Laravel Sanctum mengenali sesi SPA dengan benar.

2. **`frontend/vite.config.ts`**:
   - Menambahkan opsi `changeOrigin: true`, `cookieDomainRewrite: { '*': '' }`, dan pembersihan header CORS duplikat pada respons proxied.

## Hasil Verifikasi

1. **Automated Headless Chrome CDP Test**:
   - Seluruh alur login diuji menggunakan browser Chrome sesungguhnya melalui DevTools Protocol.
   - Panggilan `GET /sanctum/csrf-cookie` -> 204 No Content
   - Panggilan `POST /api/v1/auth/login` -> 200 OK (Set-Cookie `XSRF-TOKEN` dan `apg_crm_session`)
   - Panggilan `GET /api/v1/auth/me` -> 200 OK (User terotentikasi)
   - Panggilan `GET /api/v1/notifications/unread-count` -> 200 OK
   - Panggilan `GET /api/v1/admin/users?per_page=20` -> 200 OK
   - Redirect URL berhasil ke dashboard masing-masing role tanpa error sesi.

2. **Verifikasi Login 8 Akun & Role Default**:
   - `admin@tichub.local` (Super Admin) -> `/admin/users` (PASS)
   - `requester@tichub.local` (Requester) -> `/user/tickets` (PASS)
   - `supervisor@tichub.local` (Supervisor) -> `/supervisor-it/dashboard` (PASS)
   - `itlead@tichub.local` (IT Lead) -> `/supervisor-it/dashboard` (PASS)
   - `pic@tichub.local` (PIC) -> `/pic/dashboard` (PASS)
   - `qa@tichub.local` (QA) -> `/qa/dashboard` (PASS)
   - `manager@tichub.local` (Manager) -> `/manager/approvals` (PASS)
   - `executive@tichub.local` (Executive) -> `/executive/reports` (PASS)
   - *Semua akun menggunakan password:* `password`

3. **Backend Test Suite (PHPUnit)**:
   - 520 tests, 3,313 assertions -> **100% PASSED** (0 failures).

4. **Frontend Build & Typecheck**:
   - `npm run typecheck` -> **PASS** (0 errors)
   - `npm run build` -> **PASS**
