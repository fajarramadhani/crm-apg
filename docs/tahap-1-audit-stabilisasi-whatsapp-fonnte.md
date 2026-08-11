# Tahap 1 - Audit dan Stabilisasi Integrasi WhatsApp/Fonnte

Tanggal audit: 11 Agustus 2026
Project: APG CRM
Branch: `feature/fonnte-whatsapp-notification`
Status akhir: **READY FOR REVIEW**

## 1. Ringkasan Audit

Integrasi WhatsApp/Fonnte telah diaudit dan distabilkan pada branch aktif tanpa melakukan commit, push, merge, atau deployment.

Kondisi setelah stabilisasi:

- Provider Fonnte terisolasi melalui kontrak `WhatsAppGateway`.
- Pengiriman WhatsApp menggunakan database outbox dan queue.
- Kegagalan WhatsApp tidak membatalkan workflow tiket utama.
- Duplicate notification dibatasi menggunakan `deduplication_key` unik.
- Duplicate queue job tidak mengirim ulang pesan yang sudah diterima provider dan berstatus `pending`.
- Timeout dengan hasil ambigu tidak diulang otomatis untuk menghindari pesan ganda.
- Webhook menggunakan authentication header, validasi payload ketat, provider-scoped lookup, dan terminal-state idempotency.
- Retry manual membersihkan korelasi provider dari attempt sebelumnya.
- Token, webhook secret, raw message, dan provider identifiers tidak dikembalikan melalui API history admin.
- Seluruh backend dan frontend quality gate lulus.

Tidak ada fitur baru di luar scope stabilisasi WhatsApp/Fonnte yang ditambahkan pada tahap ini.

## 2. File yang Berubah

Working tree pada akhir audit berisi 24 modified files dan 26 untracked files. Sebagian modified file sudah berada di working tree sebelum audit dimulai.

### Gateway, Queue, Outbox, dan Notification Service

- `backend/app/Contracts/WhatsAppGateway.php`
- `backend/app/Jobs/SendWhatsAppNotificationJob.php`
- `backend/app/Services/WhatsApp/FonnteWhatsAppGateway.php`
- `backend/app/Services/WhatsAppNotificationService.php`
- `backend/app/Services/WhatsAppNotificationSettings.php`
- `backend/app/Listeners/WhatsAppNotificationSubscriber.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/config/whatsapp.php`

### Webhook dan Admin API

- `backend/app/Http/Controllers/Api/WhatsAppWebhookController.php`
- `backend/app/Http/Controllers/Api/V1/WhatsAppManagementController.php`
- `backend/routes/api.php`
- `backend/config/permissions.php`

### Persistence

- `backend/app/Models/WhatsAppNotification.php`
- `backend/app/Models/WhatsAppNotificationSetting.php`
- `backend/database/migrations/2026_08_05_000001_create_whatsapp_notifications_table.php`
- `backend/database/migrations/2026_08_06_000001_create_whatsapp_notification_settings_table.php`

### Integrasi Workflow Tiket

- `backend/app/Services/TicketClosureService.php`
- `backend/app/Services/TicketSlaEscalationService.php`
- `backend/app/Services/TicketInactivityReminderService.php`

`TicketInactivityReminderService.php` hanya memiliki perubahan formatting dan tidak menambahkan pengiriman WhatsApp untuk inactivity reminder.

### Konfigurasi dan Command

- `backend/.env.example`
- `backend/app/Console/Commands/TestWhatsAppNotificationCommand.php`
- `backend/bootstrap/app.php`
- `backend/config/sanctum.php`

### Frontend WhatsApp

- `frontend/src/pages/admin/WhatsAppSettingsPage.tsx`
- `frontend/src/services/whatsAppService.ts`
- `frontend/src/App.tsx`
- `frontend/src/components/Layout.tsx`
- `frontend/src/api/client.ts`

### Frontend Environment dan Session

- `frontend/.env.example`
- `frontend/src/config/env.ts`
- `frontend/src/context/AuthContext.tsx`
- `frontend/src/pages/Login.tsx`
- `frontend/src/repositories/authRepository.ts`
- `frontend/vite.config.ts`

Perubahan session, CSRF, proxy, dan login tersebut tidak seluruhnya spesifik WhatsApp. File-file itu sudah berada dalam working tree saat audit dimulai dan tidak dihapus atau dikembalikan.

### Automated Tests

- `backend/tests/Feature/SendWhatsAppNotificationJobTest.php`
- `backend/tests/Feature/WhatsAppConfigurationTest.php`
- `backend/tests/Feature/WhatsAppManagementApiTest.php`
- `backend/tests/Feature/WhatsAppNotificationSubscriberTest.php`
- `backend/tests/Feature/WhatsAppOutboxTest.php`
- `backend/tests/Feature/WhatsAppWebhookControllerTest.php`
- `backend/tests/Feature/TicketSlaEscalationTest.php`
- `backend/tests/Feature/ApiFoundationTest.php`
- `backend/tests/Feature/AuthenticationAndAuthorizationTest.php`

### Dokumentasi

- `docs/crm-whatsapp-message-templates.md`
- `docs/crm-whatsapp-notification-setup.md`
- `docs/crm-whatsapp-notification-stage-18-implementation-report.md`
- `docs/tahap-1-audit-stabilisasi-whatsapp-fonnte.md`

## 3. Masalah yang Ditemukan

### Security

- Nomor IT Support yang terlihat operasional terdapat di `.env.example`, dokumentasi, dan fixture test.
- File lokal `backend/.env` berisi konfigurasi non-placeholder. File tersebut di-ignore Git, tetapi tetap merupakan data sensitif pada workstation.
- Webhook secret dapat ditempatkan pada URL path sehingga berisiko masuk access log, WAF, APM, dan reverse proxy log.
- Webhook menerima secret pendek selama nilainya tidak kosong.
- API history mengembalikan `rendered_message`. Pesan requester dapat mengandung bearer tracking URL.
- Device profile dimasking di browser, tetapi API masih berpotensi mengembalikan identifier penuh.

### Webhook dan Idempotensi

- Callback `sent` dapat diubah menjadi `failed` oleh callback terminal berikutnya.
- Callback belum dibatasi ke provider `fonnte` saat lookup.
- Malformed JSON belum menghasilkan respons `400` yang terkontrol.
- Tidak ada batas ukuran body webhook.
- Retry manual mempertahankan provider message ID dari attempt lama.
- Callback lama dapat memengaruhi attempt baru.
- Provider message ID belum memiliki unique constraint tersendiri.

### Queue dan Failure Handling

- Gateway dan queue sama-sama melakukan retry sehingga timeout ambigu dapat menghasilkan beberapa pengiriman.
- Worker crash dapat meninggalkan record pada status `processing` secara permanen.
- Job belum memiliki timeout dan `failed()` handler.
- Status `pending` dipakai untuk pesan yang menunggu callback dan sebelumnya juga dapat diproses ulang oleh duplicate job.
- Queue dispatch pada `afterCommit` dapat melempar exception setelah transaksi tiket selesai.
- Retry manual dapat mengubah state menjadi `queued`, kemudian gagal saat broker queue tidak tersedia.
- Listener WhatsApp synchronous masih dapat melempar exception sebelum masuk ke `queueSafely()`.
- Event `TicketClosed` sebelumnya dipanggil dari dalam transaksi closure.
- Respons sukses Fonnte tanpa message ID dianggap valid, padahal callback tidak dapat dikorelasikan.

### Technical Debt yang Masih Tersisa

- Belum ada tabel provider attempt terpisah. Korelasi attempt masih memakai kolom pada satu outbox row.
- Belum ada reconciliation scheduler untuk record `pending` yang webhook-nya tidak pernah datang.
- Belum ada freshness atau cryptographic signature validation karena kontrak Fonnte yang digunakan hanya menyediakan shared secret.
- Provider message ID belum dijamin unik pada database.
- Nomor penerima sudah encrypted, tetapi `rendered_message` belum encrypted di database.
- Inactivity reminder tetap in-app only karena menambah event WhatsApp baru berada di luar scope stabilisasi.
- Ignored log lokal tetap harus diperlakukan sebagai artefak workstation dan tidak boleh masuk support bundle tanpa redaksi.

## 4. Perbaikan yang Dilakukan

### Webhook

- Menghapus route webhook yang menempatkan secret pada URL.
- Mewajibkan header `X-Fonnte-Webhook-Secret`.
- Mewajibkan configured secret minimal 32 karakter.
- Menambahkan batas payload 64 KiB.
- Menangani malformed JSON dengan HTTP `400`.
- Menolak top-level JSON array atau scalar.
- Mempertahankan validasi untuk ID, status, state, state ID, device, dan timestamp.
- Membatasi lookup callback ke provider `fonnte`.
- Menjadikan `sent`, `failed`, `invalid`, dan `expired` sebagai terminal state.
- Duplicate atau conflicting callback setelah terminal state tidak lagi mengubah hasil akhir.
- Unknown provider message ID dikembalikan aman sebagai `matched: false`.

### Queue dan Provider

- Menambahkan job timeout.
- Menambahkan `failOnTimeout`.
- Menambahkan `failed()` handler untuk mencatat exhausted queue attempts.
- Mendeteksi stale `processing` dan mengubahnya menjadi `DELIVERY_STATE_UNKNOWN` tanpa mengirim ulang.
- Menghentikan internal resend untuk endpoint Fonnte `/send` pada timeout ambigu.
- Timeout ambigu dicatat sebagai `DELIVERY_STATE_UNKNOWN` dan tidak di-retry otomatis.
- HTTP `408`, `425`, `429`, dan `5xx` tetap dapat diproses oleh queue retry.
- Device disconnected dan quota exhausted tidak diulang langsung karena umumnya memerlukan intervensi operasional.
- Respons sukses tanpa provider message ID diperlakukan sebagai `INVALID_RESPONSE`.
- Duplicate job tidak mengirim ulang record `pending` yang menunggu webhook.
- Retry queue menggunakan status `queued`, bukan `pending`.

### Outbox dan Workflow Isolation

- Queue dispatch pada `afterCommit` sekarang ditangkap.
- Kegagalan broker mengubah outbox menjadi `QUEUE_DISPATCH_FAILED`.
- Exception queue tidak diteruskan ke workflow tiket.
- Seluruh handler subscriber WhatsApp dibungkus non-throwing boundary.
- Log kegagalan hanya mencatat nama event, notification ID, dan exception class.
- Event `TicketClosed` sekarang dipublikasikan setelah transaksi closure selesai.
- Retry manual menangani broker failure dengan status eksplisit dan HTTP `503`.

### Retry Manual

- Menghapus provider message ID lama.
- Menghapus provider request ID dan response lama.
- Menghapus failure metadata lama.
- Mereset timestamp attempt.
- Mereset `attempts` menjadi nol.
- Mencegah callback attempt lama menemukan record melalui ID lama.

### Admin API dan Privacy

- Menghapus raw `rendered_message` dari response history.
- Menghapus provider message IDs dan request ID dari response history.
- Melakukan allowlist field device profile di backend.
- Melakukan masking identifier device di backend, bukan hanya di browser.
- Upstream device-profile failure sekarang menggunakan HTTP `502`.
- Runtime enablement ditolak jika provider, token, nomor, atau webhook secret belum aman.
- Status `ready` sekarang mempertimbangkan webhook secret yang cukup panjang.

### Environment dan Dokumentasi

- Menghapus nomor operasional dari `.env.example` dan dokumentasi.
- `FONNTE_IT_SUPPORT_NUMBER` menjadi placeholder kosong.
- Default gateway send attempts diubah menjadi satu.
- Dokumentasi tidak lagi menyarankan secret pada URL.
- Provider yang tidak mendukung custom header diarahkan melalui reverse proxy atau API gateway.
- Command test WhatsApp tidak dapat dijalankan di production, termasuk menggunakan `--force`.

## 5. Security Check

Hasil pemeriksaan keamanan:

- `backend/.env` tetap di-ignore oleh Git.
- `backend/storage/logs/*` tetap di-ignore oleh Git.
- `.vite/*.log` tetap di-ignore oleh Git.
- Tidak ditemukan Fonnte token hardcoded pada tracked atau ordinary untracked source.
- Tidak ditemukan webhook secret asli pada tracked atau ordinary untracked source.
- Tidak ditemukan lagi nomor operasional yang sebelumnya muncul pada example, dokumentasi, dan source/test.
- `.env.example` hanya berisi placeholder kosong untuk token, webhook secret, dan nomor tujuan.
- Business logic mengakses credential melalui `config('whatsapp...')`, bukan `env()` langsung.
- Pemanggilan `env()` hanya berada pada `backend/config/whatsapp.php`, sesuai pola Laravel.
- Token tidak ditulis ke log.
- Provider response disanitasi untuk field bernama token, authorization, atau secret.
- Nomor pada log hanya ditampilkan dalam bentuk masked.
- Raw message tidak lagi dikembalikan oleh history API.
- Webhook secret tidak lagi diterima melalui path atau query string.

Karena `backend/.env` lokal pernah berisi credential aktif, credential tersebut sebaiknya dirotasi jika workstation atau file pernah dibagikan kepada pihak yang tidak berwenang. Nilai credential tidak dicantumkan dalam laporan ini.

## 6. Queue dan Failure Handling

Alur pengiriman setelah stabilisasi:

1. Workflow tiket menghasilkan domain event.
2. Subscriber membentuk pesan dan membuat outbox secara aman.
3. Outbox menggunakan `deduplication_key` unik.
4. Job didispatch setelah transaksi database commit.
5. Kegagalan dispatch queue tidak menggagalkan ticket workflow.
6. Job mengambil record menggunakan transaction dan row lock.
7. Status berubah dari `queued` menjadi `processing`.
8. Provider dipanggil satu kali untuk send attempt.
9. Respons eksplisit transient seperti `5xx` mengembalikan record ke `queued` dan menggunakan queue retry.
10. Timeout atau connection loss setelah request dikirim dianggap ambigu dan tidak diulang otomatis.
11. Respons sukses Fonnte wajib memiliki message ID.
12. Record yang diterima provider menjadi `pending` atau `sent`.
13. Duplicate job pada `pending` berhenti tanpa resend.
14. Webhook memindahkan `pending` ke satu terminal state.
15. Duplicate webhook tidak mengubah terminal state.
16. Setelah maximum attempts, `failed()` mencatat `QUEUE_ATTEMPTS_EXHAUSTED`.
17. Stale `processing` dicatat sebagai `DELIVERY_STATE_UNKNOWN`.
18. Admin dapat melakukan retry manual setelah meninjau kegagalan.
19. Retry manual menghapus identitas attempt lama sebelum job baru dibuat.

Kebijakan yang disengaja: timeout ambigu lebih memilih menghindari duplicate WhatsApp daripada melakukan retry agresif. Record dapat ditinjau admin sebelum retry manual.

## 7. Hasil Testing

### Test WhatsApp Terfokus

```text
33 tests passed
124 assertions
```

Cakupan yang ditambahkan atau diperbarui:

- Header-only webhook authentication.
- Secret URL ditolak.
- Malformed JSON.
- Unknown message ID.
- Duplicate callback.
- Terminal-state idempotency.
- Timeout ambigu tidak diulang.
- Stale processing tidak dikirim ulang.
- Duplicate job tidak mengirim ulang record `pending`.
- HTTP `5xx` kembali ke queue retry.
- Device dan quota operational failure.
- Retry manual membersihkan provider IDs dan failure state.

### Full Backend Suite

```text
489 tests passed
3,191 assertions
```

Baseline sebelum stabilisasi adalah 485 test dan 3.178 assertions. Tahap ini menambahkan empat test dan 13 assertions pada full suite.

### Quality Gates

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test` | Lulus |
| Laravel Pint | Lulus |
| `corepack pnpm typecheck` | Lulus |
| `corepack pnpm format:check` | Lulus |
| `corepack pnpm build` | Lulus |
| `git diff --check` | Lulus |
| Credential pattern scan | Tidak menemukan secret pada source yang akan masuk Git |

Production build berhasil dengan entry bundle sekitar 290,59 kB dan gzip sekitar 87,72 kB.

## 8. Git Status Akhir

Ringkasan status repository setelah audit:

```text
Branch: feature/fonnte-whatsapp-notification
Modified files: 24
Untracked files: 26
Staged files: 0
```

Untracked files yang masih ada:

```text
backend/app/Console/Commands/TestWhatsAppNotificationCommand.php
backend/app/Contracts/WhatsAppGateway.php
backend/app/Http/Controllers/Api/V1/WhatsAppManagementController.php
backend/app/Http/Controllers/Api/WhatsAppWebhookController.php
backend/app/Jobs/SendWhatsAppNotificationJob.php
backend/app/Listeners/WhatsAppNotificationSubscriber.php
backend/app/Models/WhatsAppNotification.php
backend/app/Models/WhatsAppNotificationSetting.php
backend/app/Services/WhatsApp/FonnteWhatsAppGateway.php
backend/app/Services/WhatsAppNotificationService.php
backend/app/Services/WhatsAppNotificationSettings.php
backend/config/whatsapp.php
backend/database/migrations/2026_08_05_000001_create_whatsapp_notifications_table.php
backend/database/migrations/2026_08_06_000001_create_whatsapp_notification_settings_table.php
backend/tests/Feature/SendWhatsAppNotificationJobTest.php
backend/tests/Feature/WhatsAppConfigurationTest.php
backend/tests/Feature/WhatsAppManagementApiTest.php
backend/tests/Feature/WhatsAppNotificationSubscriberTest.php
backend/tests/Feature/WhatsAppOutboxTest.php
backend/tests/Feature/WhatsAppWebhookControllerTest.php
docs/crm-whatsapp-message-templates.md
docs/crm-whatsapp-notification-setup.md
docs/crm-whatsapp-notification-stage-18-implementation-report.md
frontend/src/pages/admin/WhatsAppSettingsPage.tsx
frontend/src/services/whatsAppService.ts
```

Tidak ada file yang di-stage dan tidak ada commit yang dibuat.

Git menampilkan peringatan konversi LF ke CRLF pada beberapa file frontend. `git diff --check` tetap lulus dan tidak menemukan whitespace error.

## 9. Kesimpulan

Status tahap ini:

```text
READY FOR REVIEW
```

Alasan:

- Seluruh quality gate lulus.
- Risiko utama webhook, duplicate callback, stale provider ID, timeout ambiguity, duplicate queue job, worker crash, queue dispatch failure, dan workflow rollback telah distabilkan.
- Tidak ditemukan credential yang akan masuk Git.
- Working tree masih besar dan seluruh implementasi WhatsApp masih untracked sehingga review manusia terhadap keseluruhan diff tetap diperlukan.
- Diperlukan keputusan operasional mengenai rotasi credential lokal dan penggunaan reverse proxy untuk webhook apabila Fonnte tidak mendukung custom header.
- Live Fonnte flow tidak dijalankan ulang pada tahap audit agar tidak mengirim pesan eksternal tanpa instruksi eksplisit.

Belum dilakukan commit, push, merge, release candidate, atau deployment.
