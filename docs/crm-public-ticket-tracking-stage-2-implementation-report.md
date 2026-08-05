# LAPORAN IMPLEMENTASI TAHAP 2 - TRACKING TIKET PUBLIK

## Hasil Audit

- Tahap 1 menggunakan `RequesterTicketService`, generator nomor, workflow, history, lampiran, dan idempotency yang sama dengan requester login.
- Tiket publik memiliki `requester_id = null` dan identitas snapshot.
- `ticket_comments` sudah memiliki `is_internal`.
- History menyimpan data internal sensitif sehingga resource history lama tidak digunakan untuk tracking publik.
- Tidak ditemukan tracking token, OTP, atau magic link sebelumnya.

## Strategi Implementasi

- Token diterbitkan secara atomik dalam transaksi pembuatan tiket publik.
- Tidak dibuat workflow baru.
- Tracking bersifat read-only dan tidak dapat mengubah status.
- Dibuat service khusus untuk issuance, lookup, URL, expiry, revoke, rotate, timeline, dan data publik.
- Idempotent replay mengembalikan tiket dan token yang sama tanpa membuat token aktif tambahan.
- Resource tracking dipisahkan dari `TicketResource`.

## Desain Token dan Expiry

- Nonce dibuat dengan `random_bytes(32)`.
- Raw token 256-bit diturunkan menggunakan HMAC-SHA256 dan dienkode Base64URL.
- Database hanya menyimpan SHA-256 hash raw token, bukan raw token atau URL.
- Nonce, generation, dan key version disimpan untuk menghasilkan receipt yang sama saat idempotent replay.
- Idempotency key publik wajib memiliki panjang 32-255 karakter.
- Expiry dikonfigurasi melalui:

```env
PUBLIC_TICKET_TRACKING_EXPIRY_DAYS=
PUBLIC_TICKET_TRACKING_KEY=
PUBLIC_TICKET_TRACKING_KEY_VERSION=1
```

- `PUBLIC_TICKET_TRACKING_KEY` minimal 256-bit dan wajib pada production.
- URL frontend menggunakan `FRONTEND_URL`, bukan URL production yang ditulis langsung di source code.

## Database dan Migration

Migration baru:

```text
backend/database/migrations/2026_07_31_000003_create_public_ticket_tracking_tokens.php
```

Tabel `public_ticket_tracking_tokens` mencakup:

- `ticket_id`
- `derivation_nonce`
- `generation`
- `key_version`
- `token_hash`
- `expires_at`
- `last_used_at`
- `revoked_at`
- `created_by`
- `revoked_by`
- timestamps

Tabel `public_ticket_submissions` mendapat `tracking_token_id` untuk memastikan idempotent replay menggunakan token awal yang sama.

## Route dan Endpoint

Route publik:

```text
GET /track/:token
GET /api/v1/public/tickets/track/{token}
```

Endpoint internal:

```text
POST /api/v1/supervisor-it/tickets/{ticket}/public-tracking/rotate
POST /api/v1/supervisor-it/tickets/{ticket}/public-tracking/revoke
```

Endpoint internal dilindungi oleh:

- Sanctum dan active-user middleware.
- Permission `ticket.public_tracking.manage`.
- `TicketPolicy::managePublicTracking`.
- Pembatasan `submission_source = public_form`.

## Data Publik dan Timeline

Response tracking hanya berisi:

- Nomor tiket.
- Judul.
- Cabang.
- Kategori.
- Tanggal pengajuan.
- Status publik.
- Timeline publik.
- Requester update yang diizinkan.
- Waktu pembaruan terakhir.

Status internal dipetakan menjadi label publik seperti:

- Pengajuan diterima.
- Sedang diverifikasi.
- PIC telah ditentukan.
- Sedang dikerjakan.
- Menunggu informasi.
- Menunggu pengujian.
- Pemeriksaan akhir.
- Menunggu konfirmasi requester.
- Penyelesaian diberikan.
- Tiket selesai.

Timeline berasal dari `ticket_status_histories`, kemudian dipetakan dan dideduplikasi. Notes, metadata, actor, role, dan kode status mentah tidak dikembalikan.

Requester update hanya mengambil komentar yang memenuhi seluruh ketentuan berikut:

- `is_internal = false`.
- Tidak terkait defect atau UAT finding.
- Memiliki type dalam allowlist.
- Disanitasi menjadi plain text.
- Tidak menampilkan identitas author.

## Keamanan

- Token invalid, expired, revoked, malformed, dan tidak ditemukan menghasilkan respons umum `404` yang sama.
- Rate limit tracking adalah 30 request per menit per IP.
- Header keamanan khusus:

```text
Cache-Control: no-store, private
Pragma: no-cache
Referrer-Policy: no-referrer
X-Content-Type-Options: nosniff
```

- Raw token, hash, dan tracking URL tidak dimasukkan ke history atau log aplikasi.
- Rotation mencabut seluruh token lama sebelum menerbitkan token baru.
- Revocation tidak mengembalikan secret.
- Token tidak disimpan ke `localStorage` atau `sessionStorage`.
- Halaman receipt menyediakan link selectable sebagai fallback jika clipboard gagal.
- Tidak ada endpoint status mutation berbasis tracking token.

## Penanganan UAT Requester Publik

Tracking menampilkan status:

- `Menunggu pengujian`.
- `Menunggu konfirmasi requester`.

Tracking token tidak diberi hak untuk melakukan UAT atau konfirmasi.

Simplified dynamic workflow dapat diselesaikan oleh Supervisor/PIC melalui jalur internal. Flow legacy yang mewajibkan `requester_id` untuk UAT atau konfirmasi akhir tetap memerlukan keputusan bisnis lebih lanjut.

Tidak dibuat akun palsu, bypass approval, atau synthetic confirmation karena bertentangan dengan batasan perubahan workflow.

## File yang Diubah

File utama Tahap 2:

- `backend/.env.example`
- `backend/config/public_tracking.php`
- `backend/config/permissions.php`
- `backend/database/migrations/2026_07_31_000003_create_public_ticket_tracking_tokens.php`
- `backend/app/Models/PublicTicketTrackingToken.php`
- `backend/app/Models/PublicTicketSubmission.php`
- `backend/app/Models/Ticket.php`
- `backend/app/Services/PublicTicketTrackingService.php`
- `backend/app/Services/PublicTicketStatusMapper.php`
- `backend/app/Services/PublicTicketCreationResult.php`
- `backend/app/Services/RequesterTicketService.php`
- `backend/app/Http/Controllers/Api/V1/PublicTicketController.php`
- `backend/app/Http/Controllers/Api/V1/PublicTicketTrackingController.php`
- `backend/app/Http/Resources/Api/V1/PublicTicketTrackingResource.php`
- `backend/app/Http/Requests/Api/V1/ManagePublicTicketTrackingRequest.php`
- `backend/app/Http/Middleware/AddPublicTrackingHeaders.php`
- `backend/app/Http/Middleware/AddSecurityHeaders.php`
- `backend/app/Policies/TicketPolicy.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/bootstrap/app.php`
- `backend/routes/api.php`
- `backend/tests/Feature/PublicTicketTrackingTest.php`
- `backend/tests/Feature/PublicTicketSubmissionTest.php`
- `frontend/src/components/public/PublicShell.tsx`
- `frontend/src/pages/public/PublicTicketTracking.tsx`
- `frontend/src/pages/public/PublicRequest.tsx`
- `frontend/src/services/publicTicketService.ts`
- `frontend/src/App.tsx`

## Hasil Pengujian

Backend full suite:

```text
vendor/bin/phpunit
```

Hasil: `421 passed`, `2.742 assertions`.

Targeted tracking:

```text
vendor/bin/phpunit tests/Feature/PublicTicketTrackingTest.php tests/Feature/PublicTicketSubmissionTest.php
```

Hasil: `19 passed`, `415 assertions`.

Validasi backend lainnya:

- `vendor/bin/pint --test`: lulus.
- `composer validate --no-interaction`: lulus.
- `composer audit --locked --no-interaction`: lulus.
- SQLite `migrate:fresh`: lulus.
- Rollback migration Tahap 2: lulus.
- Migrasi ulang: lulus.

Validasi frontend:

- `corepack pnpm format:check`: lulus.
- `corepack pnpm typecheck`: lulus.
- `corepack pnpm build`: lulus.
- `git diff --check`: lulus.

## Catatan dan Risiko

- MySQL belum diuji karena Docker/MySQL runner tidak tersedia.
- Browser automation desktop/mobile belum tersedia; build dan struktur responsif sudah diverifikasi.
- Token berada pada URL karena route `/track/:token` diwajibkan. Reverse proxy/APM disarankan meredaksi path tracking dari access log.
- Hash-only replayable token menggunakan nonce dan secret aplikasi. Kompromi database sekaligus tracking key memungkinkan rekonstruksi token.
- Expiry kosong berarti token tidak kedaluwarsa otomatis; production disarankan menetapkan nilai terbatas.
- Internal rotate/revoke sudah tersedia melalui API, tetapi belum dibuat panel khusus pada UI Supervisor IT.
- React Router masih memiliki advisory dependency level `high` yang sudah tercatat pada Tahap 1. Tidak ada vulnerability `critical`.
- Legacy UAT dan requester confirmation berbasis user masih memerlukan desain lanjutan.

## Status Akhir

**Selesai dengan catatan**

Tahap 2 tracking publik, token aman, idempotent replay, timeline publik, requester update, rotation/revocation, keamanan response, dan halaman React sudah selesai.

Belum ada commit, push, merge, atau deployment.
