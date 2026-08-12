# LAPORAN IMPLEMENTASI TAHAP 5 - UAT DAN KONFIRMASI REQUESTER PUBLIK

## 1. Hasil Pemeriksaan Awal Repository

- Branch aktif: `feature/crm-simplified-dynamic-workflow`.
- Branch berada pada commit yang sama dengan `origin/feature/crm-simplified-dynamic-workflow` saat pemeriksaan dimulai.
- Worktree sudah berisi perubahan lokal Tahap 3 dan Tahap 4: 21 file modified dan delapan file untracked.
- Semua perubahan existing dipertahankan. Tidak dilakukan reset, stash, checkout, revert, atau penghapusan perubahan.
- Regression awal Tahap 1-4 lulus: `34 passed`, `577 assertions`.
- Docker dan MySQL client tidak tersedia. Pengujian MySQL Tahap 5 tidak dijalankan dan tidak diklaim berhasil.
- Tidak dibuat commit atau push baru.

## 2. Hasil Audit Workflow UAT Existing

Flow requester login existing:

```text
ready_for_uat
  -> uat_assignment
  -> uat_in_progress
  -> uat_approved
```

Flow kegagalan UAT:

```text
uat_in_progress
  -> uat_failed
  -> development_in_progress
  -> uat_retest
```

Temuan audit:

- `TicketUatExecutionService` mengelola scenario, run, result, dan completion UAT.
- Flow legacy mengharuskan `uat_assignee_id` menunjuk ke user requester.
- UAT run menyimpan `requester_id`, dan result/finding menyimpan actor user.
- `TicketTransitionService` sudah memiliki semantik accepted dan rejected yang dibutuhkan.
- `ticket_status_histories.actor_id` sudah nullable sejak Tahap 1.
- `ticket_attachments.uploaded_by` sudah nullable sejak Tahap 1.
- `ticket_comments.user_id` sebelumnya tidak nullable, sehingga belum dapat mencatat feedback requester publik tanpa user palsu.
- UAT approved sudah memiliki event dan notification internal.
- UAT failure dan confirmation response belum memiliki notification publik-terverifikasi khusus.

Audit konfirmasi requester:

- Status existing adalah `awaiting_requester_confirmation`.
- Accepted membuat record `TicketRequesterConfirmation`, menandai `requester_confirmed_at`, dan tetap menunggu closure internal.
- Rejected menjalankan `reopened -> development_in_progress`.
- Controller dan policy lama mewajibkan user login dengan ID sama dengan `ticket.requester_id`.
- `TicketRequesterConfirmationService` sebelumnya mengharuskan `User $actor`.
- `TicketClosureService` tetap mensyaratkan konfirmasi accepted sebelum closure.

## 3. Matrix Status dan Tindakan

| Status internal | Label publik | Tindakan publik | Accepted | Rejected | Tindak lanjut internal |
|---|---|---|---|---|---|
| `ready_for_uat` | Menunggu pengujian | UAT | `uat_approved` | `uat_failed -> development_in_progress` | PIC/Supervisor menerima notifikasi |
| `uat_assignment` | Menunggu pengujian | UAT | `uat_approved` | `uat_failed -> development_in_progress` | Assignment user tidak diwajibkan untuk public form |
| `uat_in_progress` | Menunggu pengujian | UAT | `uat_approved` | `uat_failed -> development_in_progress` | Existing state digunakan langsung |
| `uat_retest` | Menunggu pengujian | UAT | `uat_approved` | `uat_failed -> development_in_progress` | Cycle UAT dinaikkan |
| `awaiting_requester_confirmation` | Menunggu konfirmasi requester | Konfirmasi | Tetap menunggu closure internal | `reopened -> development_in_progress` | IT internal menutup setelah accepted |
| Status lainnya | Sesuai mapper Tahap 2 | Tidak ada | Ditolak | Ditolak | Tidak ada mutasi publik |

Tracking token sendiri tetap read-only pada seluruh status.

## 4. Strategi Verifikasi Requester Publik

- Tracking token aktif mengidentifikasi satu tiket secara read-only.
- Requester memilih tindakan yang dinyatakan tersedia oleh backend.
- Requester memasukkan email tiket.
- OTP email dikirim melalui abstraction Tahap 3 `PublicHistoryOtpDelivery`.
- Challenge OTP bersifat generik agar email tidak dapat dienumerasi.
- OTP benar menghasilkan action access token dengan masa hidup singkat.
- UI menyimpan challenge dan action token hanya dalam React state.
- Tidak ada credential Tahap 5 dalam `localStorage` atau `sessionStorage`.

Endpoint:

```text
GET  /api/v1/public/tickets/track/{token}/actions
POST /api/v1/public/tickets/track/{token}/actions/challenge
POST /api/v1/public/tickets/track/{token}/actions/verify
POST /api/v1/public/tickets/track/{token}/actions/revoke
POST /api/v1/public/tickets/track/{token}/uat
POST /api/v1/public/tickets/track/{token}/confirmation
```

## 5. Mekanisme Credential Tindakan

Migration:

```text
backend/database/migrations/2026_08_04_000001_create_public_ticket_action_credentials.php
```

Tabel baru:

- `public_ticket_action_challenges`
- `public_ticket_action_access_tokens`
- `public_ticket_action_idempotencies`

Challenge dan access token terikat ke:

- Ticket.
- Tracking token record aktif.
- Branch.
- HMAC identitas email.
- Jenis tindakan `uat` atau `confirmation`.
- Hash status tiket ketika verifikasi dilakukan.

Raw OTP, challenge token, action access token, tracking token, dan secret tidak disimpan. Database hanya menyimpan hash credential dan identitas terenkripsi untuk challenge.

Konfigurasi:

```env
PUBLIC_ACTION_ACCESS_TTL_MINUTES=10
```

Action access otomatis invalid apabila:

- Expired, consumed, atau revoked.
- Tracking token revoked atau expired.
- Tracking generation bukan generation aktif terbaru.
- Status tiket berubah.
- Action tidak lagi tersedia.
- Ticket/action/identity scope tidak sesuai.

Cleanup `public-actions:cleanup` dijalankan setiap jam.

## 6. Perubahan Backend

- Menambahkan controller khusus action publik.
- Menambahkan request validation khusus challenge, verify, UAT, dan confirmation.
- Menambahkan service domain `PublicTicketActionService`.
- Menambahkan status matrix backend sebagai sumber aturan utama.
- Menambahkan `publicPhaseTransition()` pada `TicketTransitionService` untuk actor tanpa akun.
- Memperluas `TicketRequesterConfirmationService` agar menerima nullable actor tanpa mengubah caller requester login lama.
- Menambahkan action availability pada response tracking publik.
- Menambahkan response allowlist untuk action descriptor.
- Membuat `ticket_comments.user_id` nullable untuk feedback public requester terverifikasi.
- Membuat requester/deployment confirmation relation nullable untuk tiket publik.
- Menambahkan exception generik action access dan verification.
- Menambahkan rate limit challenge, verify, dan action access.
- Menambahkan security response no-store melalui middleware existing.

## 7. Perubahan Frontend

Halaman `/track/:token` sekarang mencakup:

- Tombol `Lakukan Pengujian` hanya ketika backend mengembalikan action UAT.
- Tombol `Konfirmasi Penyelesaian` hanya pada status konfirmasi.
- Dialog desktop dan bottom sheet mobile.
- Form email, OTP enam digit, cooldown resend, dan expiry.
- Pilihan accepted atau rejected.
- Catatan wajib untuk rejection.
- Upload bukti UAT opsional.
- Review dan konfirmasi sebelum submit.
- Loading dan disabled state.
- Synchronous submit lock untuk mencegah double click.
- Stable idempotency key untuk retry payload yang sama.
- Session expired dan generic API errors.
- Refresh tracking, timeline, dan action availability setelah sukses.
- Revoke action access ketika flow ditutup atau selesai.

## 8. Integrasi Attachment

- Maksimal 10 file.
- Maksimal 10 MB per file.
- Extension dan MIME allowlist:
  - PNG/JPG/JPEG
  - PDF
  - TXT/CSV
  - DOC/DOCX
  - XLS/XLSX
- Evidence hanya tersedia untuk UAT.
- File disimpan pada private ticket storage menggunakan UUID.
- `uploaded_by = null` dan `visibility = requester`.
- Category adalah `uat_evidence`.
- File dibersihkan apabila insert attachment atau transaksi utama gagal.
- Idempotent replay tidak menyimpan attachment kedua.
- Confirmation tidak menerima attachment.

## 9. Integrasi Notification

- Event baru `PublicRequesterActionCompleted` dikirim hanya setelah mutation baru berhasil.
- Retry idempotent tidak mengirim event ulang.
- Recipient resolver menargetkan PIC aktif dan Supervisor pada divisi tiket.
- Notification menyatakan jenis action dan accepted/rejected tanpa credential sensitif.
- Deduplication key memakai action, outcome, dan status akhir.
- Tidak ditambahkan provider WhatsApp.

## 10. Idempotency dan Concurrency

- Semua mutation mewajibkan `Idempotency-Key` 32-255 karakter.
- Key, action, dan request payload disimpan sebagai hash.
- File payload hash mencakup nama, ukuran, MIME, dan SHA-256 isi file.
- Replay key dan payload sama mengembalikan safe result yang sama.
- Key sama dengan payload berbeda menghasilkan `409 IDEMPOTENCY_CONFLICT`.
- Action access row, ticket row, dan tracking row dikunci selama mutation.
- Status dan tracking generation diperiksa ulang setelah lock.
- Action credential menjadi consumed setelah proses pertama.
- History, attachment, confirmation, dan notification tidak digandakan pada replay.
- Challenge diserialisasi melalui tracking row lock untuk mencegah OTP ganda saat cooldown.

## 11. Authorization

- Tidak ada permission internal yang diberikan kepada requester publik.
- Tidak ada akun requester sintetis.
- Tracking token tidak cukup untuk mutation.
- Action access token hanya berlaku untuk ticket, action, tracking generation, identity, branch, dan status yang telah diverifikasi.
- History access token Tahap 3 tidak dapat digunakan sebagai action credential.
- Requester A tidak dapat menjalankan action untuk tiket requester B.
- Controller tidak menyediakan endpoint status mutation generik.

## 12. Timeline Publik dan Audit Internal

Timeline menggunakan `PublicTicketStatusMapper` Tahap 2 dan menampilkan milestone coarse tanpa actor/metadata internal.

Feedback requester disimpan sebagai non-internal comment bertipe:

- `uat_feedback`
- `confirmation_feedback`

Public tracking allowlist hanya mengembalikan teks yang disanitasi dan timestamp.

History internal menggunakan:

- `actor_id = null`
- `actor_role = public_requester`

Timeline Supervisor menampilkan actor tersebut sebagai `Requester Publik Terverifikasi`. OTP, action token, tracking URL, hash, nonce, dan secret tidak dimasukkan ke history atau notification.

## 13. Daftar File Tahap 5

Backend utama:

- `backend/.env.example`
- `backend/config/public_history.php`
- `backend/database/migrations/2026_08_04_000001_create_public_ticket_action_credentials.php`
- `backend/app/Console/Commands/CleanupPublicTicketActionsCommand.php`
- `backend/app/Events/PublicRequesterActionCompleted.php`
- `backend/app/Exceptions/PublicActionAccessDenied.php`
- `backend/app/Exceptions/PublicActionVerificationFailed.php`
- `backend/app/Models/PublicTicketActionChallenge.php`
- `backend/app/Models/PublicTicketActionAccessToken.php`
- `backend/app/Models/PublicTicketActionIdempotency.php`
- `backend/app/Services/PublicTicketActionService.php`
- `backend/app/Services/TicketTransitionService.php`
- `backend/app/Services/TicketRequesterConfirmationService.php`
- `backend/app/Services/PublicTicketTrackingService.php`
- `backend/app/Http/Controllers/Api/V1/PublicTicketActionController.php`
- `backend/app/Http/Requests/Api/V1/RequestPublicTicketActionChallengeRequest.php`
- `backend/app/Http/Requests/Api/V1/VerifyPublicTicketActionChallengeRequest.php`
- `backend/app/Http/Requests/Api/V1/SubmitPublicTicketActionRequest.php`
- `backend/app/Http/Resources/Api/V1/PublicTicketTrackingResource.php`
- `backend/app/Listeners/TicketNotificationSubscriber.php`
- `backend/app/Services/TicketNotificationRecipientResolver.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/bootstrap/app.php`
- `backend/routes/api.php`
- `backend/routes/console.php`
- `backend/tests/Feature/PublicTicketActionTest.php`
- `backend/tests/Feature/PublicTicketTrackingTest.php`

Frontend:

- `frontend/src/pages/public/PublicTicketTracking.tsx`
- `frontend/src/services/publicTicketService.ts`
- `frontend/src/components/supervisor/TicketAuditTimeline.tsx`

Perubahan lokal Tahap 3 dan Tahap 4 tetap dipertahankan dan ikut tervalidasi.

## 14. Hasil Pengujian

Regression awal Tahap 1-4:

```text
34 passed, 577 assertions
```

Targeted Tahap 1-5 final:

```text
42 passed, 668 assertions
```

Full backend suite:

```text
vendor/bin/phpunit
```

Hasil: `444 passed`, `2.995 assertions`.

Validasi backend:

- `vendor/bin/pint --test`: lulus.
- `composer validate --no-interaction`: lulus.
- `composer audit --locked --no-interaction`: menemukan dua advisory baru pada `guzzlehttp/guzzle`:
  - High: `CVE-2026-69246` / `GHSA-v5mv-p594-2x33`.
  - Medium: `CVE-2026-69245` / `GHSA-f7vp-7xgx-4w4r`.
- Route inspection: tujuh route tracking/action publik terdaftar.

Validasi frontend:

- `corepack pnpm format:check`: lulus.
- `corepack pnpm typecheck`: lulus.
- `corepack pnpm build`: lulus.
- `corepack pnpm audit --prod --audit-level critical`: tidak menemukan vulnerability critical; satu advisory high tetap ada.

Validasi repository:

- `git diff --check`: tidak menemukan whitespace error.
- Hanya terdapat warning konversi LF/CRLF pada file frontend.

Migration SQLite:

- `migrate:fresh`: lulus.
- Rollback migration Tahap 5: lulus pada database tanpa response publik.
- Migrasi ulang Tahap 5: lulus.
- Rollback dilindungi agar menolak bila response requester publik sudah tersimpan.

MySQL dan browser:

- MySQL tidak diuji karena Docker/MySQL client tidak tersedia.
- Browser fixture publik untuk flow action belum tersedia; visual automation tidak dijalankan.
- Project tidak memiliki framework unit/component frontend.

## 15. Risiko dan Pekerjaan Pending

- Locking dan schema change `nullable()->change()` belum divalidasi pada MySQL.
- Flow visual perlu UAT manual pada desktop, tablet, dan mobile.
- Advisory Guzzle high/medium perlu audit dependency terpisah sebelum upgrade.
- Advisory frontend high tetap memerlukan audit upgrade major.
- Public UAT menggunakan sign-off accepted/rejected terstruktur, bukan scenario/run detail seperti requester login legacy. Status dan semantik transisi tetap sama.
- Existing confirmation accepted tetap menunggu closure internal sesuai workflow lama; requester publik tidak dapat menutup tiket sendiri.
- Action access token yang sudah consumed hanya dapat replay melalui idempotency key yang sama; action baru memerlukan OTP baru apabila status masih mengizinkan.
- Tracking key rotation dapat memengaruhi reconstruction receipt lama sebagaimana catatan Tahap 4.
- Reverse proxy/APM tetap harus meredaksi tracking token dari URL access log.

## 16. Status Kesiapan Tahap Berikutnya

**Selesai dengan catatan**

Requester publik dapat melakukan UAT accepted/rejected, memberikan feedback dan evidence, serta memberikan konfirmasi akhir accepted/rejected setelah OTP tanpa akun sintetis. Credential mutation terikat ketat, expiry singkat, hash-only, idempotent, dan mengikuti status workflow existing.

Tahap berikutnya sebaiknya dimulai setelah validasi MySQL, UAT visual, dan audit advisory dependency.

Tidak dilakukan commit, push, merge, rebase, reset, force-push, atau deployment.
