# LAPORAN IMPLEMENTASI TAHAP 1 - FORM PUBLIK

## 1. Hasil Audit

Arsitektur yang ditemukan:

- Backend: Laravel `13.19.0`, PHP `8.3`, dan Sanctum.
- Frontend: React `19.2.4`, React Router `7.18.1`, Vite `8.0.16`, TypeScript `5.9.3`, dan Tailwind CSS `4.2.2`.
- Form requester lama berada di `/user/create-ticket`.
- Endpoint lama adalah `POST /api/v1/requester/tickets` dan dilindungi `auth:sanctum`.
- Proses pembuatan tiket berada di `RequesterTicketService`.
- Service lama sudah menangani generator nomor `TIC-YYYYMM-NNNNNN`, database transaction, dynamic/legacy workflow, status awal, status history, lampiran private dengan nama UUID, idempotency, event, notifikasi internal, serta pembersihan file ketika transaksi gagal.
- `requester_id`, `actor_id`, dan `uploaded_by` sebelumnya wajib menunjuk ke user.
- TinyMCE sudah tersedia melalui `TicketDescriptionEditor`.
- Cabang, divisi, kategori, dan aplikasi memiliki master data aktif.
- Login requester lama tidak dihapus atau dinonaktifkan.

## 2. Strategi Implementasi

Ditambahkan route frontend publik:

```text
GET /request
```

Ditambahkan endpoint API publik:

```text
GET  /api/v1/public/ticket-form-options
POST /api/v1/public/tickets
```

Endpoint publik berada di luar middleware authentication. Endpoint internal tetap dilindungi.

`RequesterTicketService` sekarang memiliki dua adapter:

- `createTicket()` untuk requester login.
- `createPublicTicket()` untuk form publik.

Keduanya meneruskan proses ke core transaksi yang sama untuk:

- Nomor tiket.
- Workflow.
- Status awal.
- Lampiran.
- History.
- Event dan notifikasi internal.
- Rollback dan cleanup file.

Tidak ada workflow kedua dan tidak ada user baru yang dibuat untuk requester publik.

## 3. Perubahan Database

Migration baru:

```text
backend/database/migrations/2026_07_31_000002_add_public_ticket_submission_support.php
```

Perubahan tabel `tickets`:

- `requester_id` menjadi nullable.
- `requester_name` ditambahkan.
- `requester_email` ditambahkan.
- `requester_phone` ditambahkan.
- `submission_source` ditambahkan dengan default `authenticated_requester`.
- Tiket publik menggunakan `submission_source = public_form`.

Perubahan terkait audit:

- `ticket_status_histories.actor_id` menjadi nullable.
- `ticket_attachments.uploaded_by` menjadi nullable.

Tabel baru `public_ticket_submissions` menyimpan:

- Hash idempotency key.
- Hash payload.
- Relasi tiket.
- Masa kedaluwarsa.

Rollback menolak berjalan apabila masih terdapat tiket publik agar data lama tidak rusak.

## 4. File yang Diubah

Backend:

- `backend/app/Console/Commands/CleanupExpiredIdempotencyRecordsCommand.php`
- `backend/app/Http/Controllers/Api/V1/PublicTicketController.php`
- `backend/app/Http/Controllers/Api/V1/SupervisorTicketController.php`
- `backend/app/Http/Requests/Api/V1/StorePublicTicketRequest.php`
- `backend/app/Http/Resources/Api/V1/TicketResource.php`
- `backend/app/Listeners/TicketNotificationSubscriber.php`
- `backend/app/Models/PublicTicketSubmission.php`
- `backend/app/Models/Ticket.php`
- `backend/app/Policies/TicketPolicy.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Services/RequesterTicketService.php`
- `backend/app/Services/TicketNotificationRecipientResolver.php`
- `backend/bootstrap/app.php`
- `backend/routes/api.php`
- `backend/tests/Feature/PublicTicketSubmissionTest.php`
- `backend/tests/Feature/TicketNotificationSubscriberTest.php`
- `backend/database/migrations/2026_07_31_000002_add_public_ticket_submission_support.php`

Frontend:

- `frontend/src/App.tsx`
- `frontend/src/api/client.ts`
- `frontend/src/components/TicketDescriptionEditor.tsx`
- `frontend/src/components/pic/PicTicketTable.tsx`
- `frontend/src/components/supervisor/SupervisorTicketTable.tsx`
- `frontend/src/pages/itlead/UatAssignmentQueue.tsx`
- `frontend/src/pages/public/PublicRequest.tsx`
- `frontend/src/pages/supervisor/ValidationQueue.tsx`
- `frontend/src/services/publicTicketService.ts`
- `frontend/src/services/ticketService.ts`

## 5. Keamanan

- Rate limit maksimal 5 submission per 15 menit per IP.
- Contact-based limiter tidak diterapkan karena input kontak yang belum tervalidasi dapat digunakan untuk memblokir kontak orang lain.
- Honeypot `website` harus kosong.
- Master data divalidasi sebagai record aktif.
- Nama, judul, kontak, URL, dan referensi menolak HTML.
- Deskripsi disanitasi menggunakan allowlist backend.
- Script, iframe, image event handler, dan link `javascript:` dibuang.
- Email dinormalisasi ke lowercase.
- WhatsApp dinormalisasi ke format `62...`.
- Minimal email atau WhatsApp wajib tersedia.
- Idempotency key mencegah double submit pada backend.
- Tombol submit dinonaktifkan selama request berjalan.
- Lampiran maksimal 10 file dan 10 MB per file.
- Tipe executable, script, HTML, dan arsip tidak diterima.
- Nama penyimpanan menggunakan UUID.
- Penyimpanan tetap private dan mekanisme download internal tidak dibuka ke publik.
- Receipt publik tidak mengembalikan ID database, workflow, PIC, atau data internal.
- Kontak requester hanya ditampilkan kepada role internal yang memiliki izin teknis atau validasi.
- Tidak ada perubahan CORS global.

## 6. Hasil Pengujian

Backend:

```text
vendor/bin/phpunit
```

Hasil: `413 passed`, `2.460 assertions`.

Targeted public dan requester regression:

```text
vendor/bin/phpunit tests/Feature/PublicTicketSubmissionTest.php ...
```

Hasil: `34 passed`, `274 assertions`.

Validasi lainnya:

```text
vendor/bin/pint --test
composer validate --no-interaction
composer audit --locked --no-interaction
php artisan route:list --path=api/v1/public
```

Semua lulus.

Migration SQLite:

- `migrate:fresh`: lulus.
- Rollback migration Tahap 1: lulus.
- Migrasi ulang: lulus.

Frontend:

```text
corepack pnpm format:check
corepack pnpm typecheck
corepack pnpm build
```

Semua lulus.

Pengujian mencakup:

- Akses anonim.
- Endpoint internal tetap membutuhkan login.
- Method API yang salah.
- Master data aktif.
- Validasi nama, cabang, kategori, kontak, judul, dan deskripsi.
- Empty rich text.
- Normalisasi email dan WhatsApp.
- Honeypot.
- Rate limit.
- XSS script, image event, JavaScript URL, dan iframe.
- Upload executable.
- Idempotent replay dan payload conflict.
- Tidak ada user baru.
- Ticket history dan attachment actor publik.
- Tampilan tiket pada antrean supervisor.
- Snapshot requester dan `submission_source`.

## 7. Regression Test

Full backend suite lulus sehingga flow berikut tetap tervalidasi:

- Login requester lama.
- Form requester login.
- Generator nomor tiket.
- Dashboard dan queue Supervisor.
- Assignment PIC.
- Workflow dan perubahan status.
- Approval.
- Notifikasi internal.
- Idempotency requester lama.
- History dan tiket lama.
- Reporting dan statistik yang sudah memiliki test.

Frontend lama tetap tersedia di `/user/create-ticket`.

## 8. Catatan dan Risiko

- Pengujian MySQL lokal belum dijalankan karena Docker/MySQL runner tidak tersedia. Migration lifecycle SQLite sudah lulus.
- Pemeriksaan visual otomatis desktop/tablet/mobile belum dijalankan karena project tidak memiliki browser test publik. Struktur responsif dan build sudah diverifikasi.
- Audit frontend menemukan advisory `high` pada React Router `7.18.1`. Perbaikannya membutuhkan React Router `8.3.0`, sehingga tidak dinaikkan pada tahap ini karena merupakan perubahan major di luar scope. Tidak ada vulnerability `critical`.
- Lampiran publik belum memiliki antivirus/content scanning. MIME, ukuran, extension, private storage, dan random filename sudah diterapkan.
- Tiket publik tidak mempunyai akun requester. Jalur simplified dynamic workflow dapat diproses oleh Supervisor/PIC sampai terminal tanpa akun, tetapi jalur legacy yang secara khusus membutuhkan requester untuk UAT atau konfirmasi akhir masih memerlukan keputusan bisnis pada tahap berikutnya. UI UAT mencegah assignment invalid ke requester tanpa akun.
- Email domain kantor tidak dibatasi karena project belum mempunyai konfigurasi allowed corporate domains. Tidak dibuat hardcoded domain.
- Tidak ada tracking token, OTP, magic link, histori publik, atau notifikasi WhatsApp.

## 9. Status Akhir

**Selesai dengan catatan**

Form publik, API, workflow intake, keamanan dasar, idempotensi, receipt, dan integrasi dashboard internal selesai. Catatan utama adalah pengujian MySQL/visual dan keputusan untuk tahapan legacy yang membutuhkan aksi requester berakun.

Tidak ada commit, push, merge, atau deployment.

## 10. Rekomendasi Tahap Berikutnya

Tahap berikutnya dapat melanjutkan ke:

**TAHAP 2 - LINK TRACKING TIKET DENGAN TOKEN AMAN**

Tahap 2 sebaiknya mencakup:

- Token acak berentropi tinggi yang disimpan dalam bentuk hash.
- Endpoint tracking dengan response minimal.
- Expiry dan rotasi token.
- Rate limit tracking.
- Mekanisme aman untuk kebutuhan informasi tambahan atau konfirmasi requester publik.
- Penanganan jalur UAT/konfirmasi untuk tiket tanpa akun.
