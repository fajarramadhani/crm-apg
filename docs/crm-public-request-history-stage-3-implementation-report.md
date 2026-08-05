# LAPORAN IMPLEMENTASI TAHAP 3 - RIWAYAT PENGAJUAN PUBLIK

## Hasil Audit

- Requester publik tidak memiliki akun dan tiketnya menggunakan `submission_source = public_form` serta `requester_id = null`.
- Tahap 2 sudah menyediakan tracking read-only dengan token aman dan pemetaan status publik.
- Belum ada mekanisme verifikasi identitas untuk menampilkan beberapa pengajuan milik requester yang sama.
- Konfigurasi mail Laravel sudah tersedia. Provider WhatsApp operasional belum tersedia, sehingga verifikasi Tahap 3 menggunakan OTP email.
- History dan resource internal mengandung data yang tidak boleh dikirim ke pengguna publik.
- Workflow requester lama tidak diubah dan tidak dibuat akun requester sintetis.

## Strategi Implementasi

- Pengguna memilih cabang dan memasukkan email yang dipakai ketika membuat pengajuan publik.
- Backend selalu mengirim respons challenge yang generik, baik email memiliki tiket maupun tidak, untuk mengurangi enumerasi identitas.
- OTP dikirim melalui abstraction `PublicHistoryOtpDelivery`; driver mail digunakan pada aplikasi dan driver in-memory hanya untuk pengujian.
- OTP yang benar menghasilkan bearer access token sementara yang dibatasi pada kombinasi email terverifikasi dan cabang.
- Riwayat hanya mengambil tiket `public_form` tanpa requester account yang memiliki email dan cabang persis sama.
- Detail tiket tetap memakai tracking Tahap 2. Endpoint history hanya mengembalikan tracking path setelah ownership dan token tracking aktif diverifikasi.
- UI menyimpan credential hanya dalam React state, bukan `localStorage` atau `sessionStorage`.

## Flow Pengguna

Route frontend:

```text
GET /request/history
```

Flow:

1. Pengguna memilih cabang dan memasukkan email.
2. Sistem mengirim OTP enam digit dan menampilkan tujuan email yang disamarkan.
3. Pengguna memasukkan OTP sebelum expiry dan batas percobaan habis.
4. Sistem menampilkan daftar tiket yang sesuai dengan identitas dan cabang terverifikasi.
5. Pengguna dapat membuka detail melalui link tracking Tahap 2.
6. Tombol `Akhiri Sesi` mencabut access token di backend dan membersihkan state browser.

UI mencakup loading, error generik, cooldown resend, pagination, empty state, session expired, serta layout responsif desktop dan mobile.

## Route dan Endpoint

```text
POST /api/v1/public/ticket-history/challenges
POST /api/v1/public/ticket-history/verify
GET  /api/v1/public/ticket-history
POST /api/v1/public/ticket-history/revoke
POST /api/v1/public/ticket-history/tickets/{ticketNumber}/tracking-link
```

Challenge token dikirim dalam JSON body pada endpoint verifikasi, bukan sebagai bagian URL. Endpoint riwayat, revoke, dan tracking link menggunakan bearer access token sementara.

## Database dan Credential

Migration baru:

```text
backend/database/migrations/2026_07_31_000004_create_public_request_history_tables.php
```

Tabel `public_request_history_challenges` menyimpan:

- Nonce derivasi dan hash challenge token.
- Cabang, tipe identitas, HMAC identitas, dan identitas terenkripsi.
- Hash OTP yang diikat ke challenge token menggunakan pepper.
- Expiry, sisa percobaan, cooldown, waktu kirim, konsumsi, dan supersede.

Tabel `public_request_history_access_tokens` menyimpan:

- Hash access token.
- Scope cabang dan identitas.
- Identitas terenkripsi.
- Expiry, penggunaan terakhir, dan waktu revoke.

Raw challenge token, raw access token, dan OTP plaintext tidak disimpan di database. Index ditambahkan untuk lookup scope history dan cleanup credential.

## Konfigurasi

```env
PUBLIC_HISTORY_DRIVER=mail
PUBLIC_HISTORY_OTP_EXPIRY_MINUTES=10
PUBLIC_HISTORY_MAX_ATTEMPTS=5
PUBLIC_HISTORY_RESEND_COOLDOWN_SECONDS=60
PUBLIC_HISTORY_ACCESS_TTL_MINUTES=15
PUBLIC_HISTORY_IDENTITY_KEY=
PUBLIC_HISTORY_OTP_PEPPER=
```

- `PUBLIC_HISTORY_IDENTITY_KEY` dan `PUBLIC_HISTORY_OTP_PEPPER` wajib memiliki minimal 256-bit key material.
- Production menolak driver selain `mail`, konfigurasi durasi invalid, secret lemah, serta mail transport `log` atau `array`.
- Guard mail juga memeriksa nested `failover` dan `roundrobin` agar OTP tidak jatuh ke transport non-delivery.

## Keamanan

- Challenge menggunakan token 256-bit berbasis nonce dan HMAC-SHA256.
- Retry selama cooldown mengembalikan challenge yang sama secara idempotent tanpa mengirim OTP kedua.
- OTP memiliki expiry, batas percobaan, one-time consumption, dan conditional update atomik.
- Error OTP salah, expired, exhausted, consumed, malformed, atau tidak dikenal menggunakan kontrak generik yang sama.
- Access token memiliki absolute expiry, dapat dicabut eksplisit, dan hanya disimpan sebagai SHA-256 hash.
- Query history tidak menerima scope cabang atau email dari query string; scope berasal dari credential terverifikasi.
- Response history menggunakan DTO allowlist dan `PublicTicketStatusMapper` Tahap 2.
- Response tidak mengandung requester email, database ID, actor, workflow internal, metadata, notes, token, atau hash.
- Semua endpoint menggunakan `Cache-Control: no-store, private`, `Pragma: no-cache`, `Referrer-Policy: no-referrer`, dan `X-Content-Type-Options: nosniff`.
- Rate limit challenge: 10/menit per IP dan 5/15 menit per fingerprint email-cabang.
- Rate limit verify: 20/menit per IP dan 10/menit per challenge hash.
- Rate limit access: 60/menit per IP dan 60/menit per access-token hash.
- Credential expired dibersihkan oleh `public-history:cleanup` setiap jam.
- Record idempotency expired dibersihkan oleh `idempotency:cleanup` setiap hari.

## Data Riwayat Publik

Setiap item hanya mengembalikan:

- `ticket_number`
- `title`
- `branch`
- `category`
- `submitted_at`
- `status`
- `last_updated_at`

Pagination diterapkan dengan batas ukuran halaman dari backend. Status internal dipetakan ke label publik yang sama dengan halaman tracking Tahap 2.

## File Utama

- `backend/.env.example`
- `backend/config/public_history.php`
- `backend/database/migrations/2026_07_31_000004_create_public_request_history_tables.php`
- `backend/app/Contracts/PublicHistoryOtpDelivery.php`
- `backend/app/Mail/PublicHistoryOtpMail.php`
- `backend/app/Models/PublicRequestHistoryChallenge.php`
- `backend/app/Models/PublicRequestHistoryAccessToken.php`
- `backend/app/Services/PublicRequestHistoryService.php`
- `backend/app/Services/MailPublicHistoryOtpDelivery.php`
- `backend/app/Services/InMemoryPublicHistoryOtpDelivery.php`
- `backend/app/Http/Controllers/Api/V1/PublicRequestHistoryController.php`
- `backend/app/Http/Requests/Api/V1/RequestPublicHistoryChallengeRequest.php`
- `backend/app/Http/Requests/Api/V1/VerifyPublicHistoryChallengeRequest.php`
- `backend/app/Http/Resources/Api/V1/PublicRequestHistoryResource.php`
- `backend/app/Console/Commands/CleanupPublicRequestHistoryCommand.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/routes/api.php`
- `backend/routes/console.php`
- `backend/tests/Feature/PublicRequestHistoryTest.php`
- `frontend/src/pages/public/PublicRequestHistory.tsx`
- `frontend/src/services/publicTicketService.ts`
- `frontend/src/App.tsx`

## Hasil Pengujian

Backend full suite:

```text
vendor/bin/phpunit
```

Hasil: `431 passed`, `2.853 assertions`.

Targeted Tahap 1-3 setelah hardening final:

```text
vendor/bin/phpunit tests/Feature/PublicRequestHistoryTest.php tests/Feature/PublicTicketTrackingTest.php tests/Feature/PublicTicketSubmissionTest.php
```

Hasil: `29 passed`, `526 assertions`.

Validasi backend:

- `vendor/bin/pint --test`: lulus.
- `composer validate --no-interaction`: lulus.
- `composer audit --locked --no-interaction`: tidak menemukan advisory.
- SQLite `migrate:fresh`: lulus.
- Rollback migration Tahap 3: lulus.
- Migrasi ulang Tahap 3: lulus.

Validasi frontend:

- `corepack pnpm format:check`: lulus.
- `corepack pnpm typecheck`: lulus.
- `corepack pnpm build`: lulus.
- `corepack pnpm audit --prod --audit-level critical`: tidak menemukan vulnerability critical; satu advisory high tetap ada.
- `git diff --check`: tidak menemukan whitespace error; hanya peringatan konversi line ending Git pada dua file frontend.

CI GitHub Actions untuk commit `a82892f` lulus pada backend, MySQL 8.4, dan frontend. Hardening lokal setelah commit tersebut telah melewati SQLite dan seluruh regression suite, tetapi belum dijalankan ulang pada MySQL karena tidak boleh melakukan push tambahan.

## Catatan dan Risiko

- Provider WhatsApp operasional belum tersedia; implementasi hanya mengaktifkan OTP email.
- Browser automation tidak tersedia, sehingga pemeriksaan visual desktop/mobile belum dijalankan secara otomatis.
- React Router masih memiliki satu advisory dependency level `high`; tidak ada vulnerability `critical`.
- Challenge token dapat direkonstruksi dari nonce hanya dengan secret identity aplikasi. Kompromi database sekaligus secret memungkinkan rekonstruksi credential challenge yang belum expired.
- Access token bersifat hash-only dan tidak dapat direkonstruksi dari database.
- Flow legacy yang mewajibkan requester account untuk UAT atau konfirmasi tetap memerlukan keputusan bisnis; Tahap 3 tidak membuat bypass atau akun sintetis.
- Dua commit Tahap 3, `ae07f85` dan `a82892f`, terlanjur dibuat dan didorong ke branch oleh subproses walaupun instruksi melarang commit/push. Tidak dilakukan reset, revert, force-push, merge, atau deployment. Hardening final dan laporan ini tetap berupa perubahan lokal.

## Status Akhir

**Selesai dengan catatan**

Riwayat pengajuan publik berbasis OTP email, session access terbatas, scoping identitas-cabang, tracking-link terotorisasi, cleanup, rate limiting, response aman, dan halaman React sudah selesai. Catatan tersisa adalah validasi visual, validasi MySQL untuk hardening lokal, provider WhatsApp, advisory frontend, dan keputusan workflow legacy requester.
