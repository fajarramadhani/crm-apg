# LAPORAN IMPLEMENTASI TAHAP 4 - PANEL PENGELOLAAN AKSES PUBLIK

## 1. Hasil Pemeriksaan Awal Repository

- Branch aktif: `feature/crm-simplified-dynamic-workflow`.
- Branch tidak memiliki staged changes dan berada pada commit yang sama dengan `origin/feature/crm-simplified-dynamic-workflow` ketika pemeriksaan dimulai.
- Worktree sudah memiliki 12 file modified dari hardening Tahap 3 dan tiga laporan untracked:
  - `docs/crm-public-request-form-stage-1-implementation-report.md`
  - `docs/crm-public-ticket-tracking-stage-2-implementation-report.md`
  - `docs/crm-public-request-history-stage-3-implementation-report.md`
- Seluruh perubahan existing dipertahankan. Tidak dilakukan reset, checkout, stash, revert, atau penghapusan perubahan.
- Regression awal Tahap 1-3 lulus: `29 passed`, `526 assertions`.
- Docker dan MySQL client tidak tersedia pada environment lokal. Pengujian MySQL Tahap 4 tidak dijalankan dan tidak diklaim berhasil.
- Dua commit Tahap 3, `ae07f85` dan `a82892f`, sudah berada pada branch sebelum Tahap 4. Tahap 4 tidak membuat commit atau push baru.

## 2. Hasil Audit Fitur Tracking Existing

Tahap 2 sudah menyediakan:

- Model `PublicTicketTrackingToken` dengan nonce derivasi, generation, key version, token hash, expiry, last used, revoke, creator, dan revoker.
- `PublicTicketTrackingService` untuk issue, receipt reconstruction, lookup publik, rotate, dan revoke.
- Token 256-bit yang disimpan sebagai SHA-256 hash, bukan raw token.
- `receipt()` yang merekonstruksi token dari nonce dan secret, lalu memverifikasi integritas terhadap hash tersimpan.
- Endpoint internal rotate dan revoke dengan authentication, permission, dan `TicketPolicy::managePublicTracking`.
- Audit rotate dan revoke melalui `ticket_status_histories`.
- Endpoint riwayat Tahap 3 yang hanya menghasilkan tracking link aktif untuk tiket milik identitas dan cabang terverifikasi.

Gap yang ditemukan:

- Belum ada endpoint internal untuk membaca status akses tracking.
- Belum ada tindakan membuat ulang link setelah expired atau revoked.
- Belum ada DTO internal khusus status tracking.
- Retry rotate dapat membuat generasi tambahan dan membatalkan hasil request pertama.
- Retry revoke dapat membuat audit duplikat.
- Aktivitas pembuatan tracking belum diaudit.
- UI detail Supervisor belum memiliki panel pengelolaan.
- Tabel Sanctum `personal_access_tokens` belum tersedia, sehingga bearer token asing pada endpoint internal menghasilkan database error, bukan `401`.

## 3. Strategi Implementasi

- Tetap menggunakan `PublicTicketTrackingService`, model, derivasi token, dan receipt Tahap 2.
- Menambahkan status resource allowlist dan endpoint internal status/issue.
- Link aktif direkonstruksi melalui `receipt()` dan hanya dikembalikan kepada pengguna internal yang terotorisasi.
- Jika rekonstruksi aman gagal, endpoint tidak membocorkan material credential dan UI menawarkan rotasi.
- Setiap issue, rotate, dan revoke internal mewajibkan `Idempotency-Key` 32-255 karakter.
- Hasil issue/rotate dikaitkan ke idempotency record melalui token record ID sehingga retry dapat merekonstruksi response yang sama tanpa menyimpan raw token atau URL.
- Ticket row dikunci selama mutasi agar hanya ada satu token aktif setelah issue atau rotate.
- Audit tetap memakai `ticket_status_histories`; tidak dibuat sistem audit kedua.
- Panel dibuat sebagai komponen terpisah di detail tiket Supervisor dan dirender berdasarkan permission efektif, bukan role hardcoded.

## 4. Perubahan Backend

Endpoint internal:

```text
GET  /api/v1/supervisor-it/tickets/{ticket}/public-tracking
POST /api/v1/supervisor-it/tickets/{ticket}/public-tracking
POST /api/v1/supervisor-it/tickets/{ticket}/public-tracking/rotate
POST /api/v1/supervisor-it/tickets/{ticket}/public-tracking/revoke
```

Perubahan utama:

- Menambahkan `PublicTrackingAccessResource` untuk status `never_issued`, `active`, `expired`, atau `revoked`.
- Resource hanya mengembalikan source, status, timestamps, URL aktif yang dapat direkonstruksi, dan allowed actions.
- Menambahkan issue ulang setelah link revoked atau expired.
- Memindahkan authorization ticket-specific ke `ManagePublicTicketTrackingRequest` sebelum validasi mutation.
- Menambahkan validasi `Idempotency-Key` dan reason.
- Menambahkan mutation throttle pada issue, rotate, dan revoke.
- Menjadikan public tracking creation transactional dengan ticket lock.
- Menambahkan idempotent replay selama 24 jam melalui `idempotency_records`.
- Menambahkan audit `public_tracking_created`, `public_tracking_rotated`, dan `public_tracking_revoked`.
- Revoke berulang dengan key yang sama tidak membuat audit duplikat.
- Alasan audit meredaksi URL, token Base64URL 43 karakter, dan nilai hex 64 karakter.
- Menambahkan migration Sanctum standar agar bearer token yang tidak valid ditolak dengan `401`, bukan database error.

## 5. Perubahan Frontend

Panel baru berada pada detail tiket Supervisor IT dan mencakup:

- Badge `Pengajuan Publik` atau `Pengajuan Internal` pada ringkasan tiket.
- Status link aktif, kedaluwarsa, revoked, atau belum dibuat.
- Tanggal dibuat, expiry, terakhir digunakan, dan revoke.
- Link aktif dalam input read-only yang selectable.
- Tombol salin dan buka link.
- Fallback selectable saat Clipboard API gagal.
- Tombol buat link baru, rotasi, dan cabut sesuai state backend.
- Dialog konfirmasi dengan reason wajib untuk semua mutasi.
- Peringatan bahwa link lama tidak berlaku setelah rotasi.
- Loading, disabled state, error state, retry status, dan toast feedback.
- Layout satu kolom pada mobile dan action row responsif pada layar lebih besar.
- Label aktivitas tracking yang mudah dibaca pada audit timeline existing.

Tracking URL hanya disimpan dalam React component state selama halaman aktif. Tidak ada penggunaan `localStorage` atau `sessionStorage`.

## 6. Mekanisme Authorization

Seluruh endpoint internal tetap berada di dalam middleware:

- `auth:sanctum`
- `active`
- `permission:ticket.public_tracking.manage`
- `public_tracking_headers`

Authorization ticket-specific tetap menggunakan:

```text
TicketPolicy::managePublicTracking
```

Policy mensyaratkan:

- User memiliki permission `ticket.public_tracking.manage`.
- Tiket memiliki `submission_source = public_form`.

Frontend juga memeriksa permission efektif melalui `hasPermission('ticket.public_tracking.manage')` dan source tiket. Backend tetap menjadi sumber otorisasi utama. PIC, requester, dan pengguna tanpa permission menerima `403`; pengguna tanpa session menerima `401`.

## 7. Mekanisme Rotate dan Revoke

Issue dan rotate:

- Mengunci user untuk serialisasi idempotency dan ticket untuk serialisasi token generation.
- Menggunakan derivasi token Tahap 2 tanpa menyimpan raw token.
- Rotate mensyaratkan token aktif dan mencabut seluruh token lama sebelum membuat generation baru.
- Retry dengan user, action, key, ticket, dan reason yang sama mengembalikan URL yang sama.
- Reuse key dengan payload berbeda menghasilkan `409 IDEMPOTENCY_CONFLICT`.
- Issue ditolak jika link aktif masih tersedia.
- Rotate ditolak jika tidak ada link aktif.

Revoke:

- Mencabut seluruh token yang belum revoked.
- Tidak mengembalikan token, URL, hash, secret, atau nonce.
- Retry idempotent tidak membuat audit tambahan.
- Link revoked menghasilkan response tracking publik `404` yang sama dengan token invalid atau expired.

## 8. Integrasi dengan Riwayat Publik

- Endpoint history tetap memverifikasi ownership berdasarkan email terverifikasi dan cabang.
- Setelah rotate, history hanya merekonstruksi generation aktif terbaru.
- Link lama langsung menghasilkan `404`.
- Setelah revoke, history tidak dapat menghasilkan tracking link.
- Requester A tidak dapat memperoleh link tiket requester B.
- Bearer access token riwayat tidak dapat memanggil endpoint issue, rotate, atau revoke internal.
- History session tetap read-only dan tidak memiliki kemampuan mengubah status tiket.

## 9. Daftar File yang Diubah

File Tahap 4:

- `backend/app/Http/Controllers/Api/V1/PublicTicketTrackingController.php`
- `backend/app/Http/Requests/Api/V1/ManagePublicTicketTrackingRequest.php`
- `backend/app/Http/Resources/Api/V1/PublicTrackingAccessResource.php`
- `backend/app/Models/IdempotencyRecord.php`
- `backend/app/Services/PublicTicketTrackingService.php`
- `backend/database/migrations/2026_08_03_000001_add_public_tracking_token_to_idempotency_records.php`
- `backend/database/migrations/2026_08_03_000002_create_personal_access_tokens_table.php`
- `backend/routes/api.php`
- `backend/tests/Feature/PublicTicketTrackingTest.php`
- `backend/tests/Feature/PublicRequestHistoryTest.php`
- `frontend/src/components/supervisor/PublicTrackingAccessPanel.tsx`
- `frontend/src/components/supervisor/TicketAuditTimeline.tsx`
- `frontend/src/components/supervisor/TicketRequesterSummary.tsx`
- `frontend/src/pages/supervisorIt/SupervisorTicketDetail.tsx`
- `frontend/src/services/ticketService.ts`

File hardening Tahap 3 yang sudah modified sebelum Tahap 4 tetap dipertahankan dan ikut tervalidasi oleh full regression suite.

## 10. Hasil Seluruh Pengujian

Pemeriksaan awal Tahap 1-3:

```text
29 passed, 526 assertions
```

Targeted Tahap 1-4 setelah implementasi:

```text
34 passed, 577 assertions
```

Full backend suite:

```text
vendor/bin/phpunit
```

Hasil: `436 passed`, `2.904 assertions`.

Validasi backend:

- `vendor/bin/pint --test`: lulus.
- `composer validate --no-interaction`: lulus.
- `composer audit --locked --no-interaction`: tidak menemukan advisory.
- Route list menampilkan status, issue, rotate, dan revoke internal.

Validasi frontend:

- `corepack pnpm format:check`: lulus.
- `corepack pnpm typecheck`: lulus.
- `corepack pnpm build`: lulus.
- `corepack pnpm audit --prod --audit-level critical`: tidak menemukan vulnerability critical; satu advisory high tetap ada.

Validasi repository:

- `git diff --check`: tidak menemukan whitespace error.
- Git hanya menampilkan peringatan konversi LF ke CRLF pada beberapa file frontend.

Migration SQLite:

- `migrate:fresh`: lulus.
- Rollback dua migration Tahap 4: lulus.
- Migrasi ulang dua migration Tahap 4: lulus.

MySQL:

- Tidak dijalankan karena Docker dan MySQL client tidak tersedia pada environment ini.

Browser/component test:

- Project tidak memiliki Vitest, Jest, React Testing Library, Playwright, atau Cypress.
- Browser automation tidak dijalankan karena fixture publik untuk detail Supervisor belum tersedia.
- Responsiveness diverifikasi melalui struktur Tailwind, typecheck, dan production build, bukan visual automation.

## 11. Risiko dan Pekerjaan Pending

- Migration dan locking Tahap 4 belum divalidasi langsung pada MySQL.
- Pemeriksaan visual interaktif pada viewport desktop, tablet, dan mobile masih perlu dilakukan ketika browser fixture tersedia.
- React Router masih memiliki satu advisory dependency level `high`; tidak dilakukan major upgrade di luar scope.
- Receipt generation lama bergantung pada tracking key version yang tersimpan, tetapi konfigurasi belum menyediakan key ring multi-version. Rotasi application secret dapat membuat URL lama tidak dapat direkonstruksi oleh panel walaupun hash lookup publik tetap bekerja.
- Possession atas history access token Tahap 3 yang masih valid memungkinkan pemiliknya meminta link generation terbaru. Token tersebut memiliki TTL pendek dan dapat dicabut, tetapi kebijakan untuk otomatis mencabut semua history session saat tracking rotation dapat dipertimbangkan pada tahap berikutnya.
- Tidak ada database partial unique constraint lintas SQLite/MySQL untuk satu token aktif. Invariant dijaga melalui transaction dan ticket row lock; concurrency MySQL tetap perlu diuji.
- Raw tracking URL tetap mengandung bearer secret pada path. Reverse proxy dan APM harus meredaksi path tracking dari access log.
- Tidak ada provider WhatsApp dan tidak ada perubahan workflow, sesuai batasan Tahap 4.

## 12. Status Kesiapan Tahap Berikutnya

**Selesai dengan catatan**

Panel pengelolaan akses publik siap untuk UAT internal setelah validasi MySQL dan pemeriksaan visual. Supervisor yang memiliki permission dapat melihat status, menyalin link aktif, issue ulang, rotate, revoke, dan melihat audit aktivitas tanpa akses ke hash, nonce, secret, OTP, atau history access token.

Tidak dilakukan commit, push, merge, rebase, reset, force-push, atau deployment pada Tahap 4.
