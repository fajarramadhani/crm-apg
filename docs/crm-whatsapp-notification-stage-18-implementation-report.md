# Laporan Implementasi Tahap 18 - Integrasi Notifikasi WhatsApp Fonnte

## 1. Ringkasan

Integrasi yang selesai menggunakan Fonnte, bukan Meta WhatsApp Cloud API. Implementasi mencakup gateway HTTP Fonnte, outbox database, job antrean, pembaruan status melalui webhook, pengaturan runtime berbasis database, halaman administrasi, pesan uji, serta notifikasi dari event tiket dan pemindaian SLA.

Status dokumen ini adalah laporan implementasi. Panduan deployment dan operasi berada di `docs/crm-whatsapp-notification-setup.md`; isi template final berada di `docs/crm-whatsapp-message-templates.md`.

## 2. Ringkasan dokumentasi resmi Fonnte

Sumber resmi yang diaudit:

- Sending API Messages: <https://docs.fonnte.com/api-send-message/>
- API Device Profile: <https://docs.fonnte.com/api-device-profile/>
- Device dashboard dan koneksi QR: <https://docs.fonnte.com/device/>
- Token/API key: <https://docs.fonnte.com/token-api-key/>
- Webhook Update Message Status: <https://docs.fonnte.com/webhook-update-message-status/>

Ringkasan kesesuaian implementasi:

- Pengiriman dilakukan dengan `POST https://api.fonnte.com/send`, token langsung pada header `Authorization` tanpa skema `Bearer`, dan field `target`, `message`, `countryCode`, serta `connectOnly`.
- Profil perangkat diperiksa dengan `POST https://api.fonnte.com/device` menggunakan header `Authorization` yang sama.
- Respons kirim sukses dapat memuat `id[]`, `requestid`, dan `process`; nilai tersebut disimpan untuk pelacakan status.
- Webhook status resmi mendokumentasikan payload `device`, `id`, `stateid`, `status`, dan `state`. Backend menerima `timestamp` secara opsional dan selalu mencatat waktu penerimaan server.
- Dokumentasi resmi yang ditinjau tidak mendokumentasikan signature webhook maupun dukungan penambahan custom header. Karena endpoint APG mewajibkan `X-Fonnte-Webhook-Secret`, deployment tidak boleh mengklaim Fonnte dapat mengirim header ini secara native. Jika dashboard Fonnte tidak menyediakan custom header, gunakan reverse proxy/gateway yang menerima webhook Fonnte, membatasi dan memvalidasi trafik semampunya, lalu menyuntikkan header tersebut saat meneruskan request ke APG.
- Token Fonnte memberi kemampuan mengirim pesan dari perangkat dan harus diperlakukan sebagai secret. Token tidak ditampilkan oleh API administrasi APG.

## 3. Audit proyek

Kondisi awal dan keputusan:

- Domain event tiket, scan SLA terjadwal, data nomor requester/PIC, tracking URL publik, autentikasi Sanctum, permission, dan infrastruktur queue Laravel sudah tersedia untuk dipakai ulang.
- Dokumen WhatsApp sebelumnya menjelaskan Meta Cloud API, fake driver, template Meta, app secret, verify token, phone number ID, dan callback Meta. Semua itu tidak sesuai implementasi final dan telah dihapus dari tiga dokumen WhatsApp.
- Abstraksi `WhatsAppGateway` dipertahankan, tetapi provider yang didukung dan di-bind saat ini hanya `fonnte`; tidak ada fake, null, atau Meta gateway.
- Notifikasi dibuat sebagai outbox setelah transaksi commit, dideduplikasi dengan hash key unik, lalu dikirim melalui queue `notifications`.
- Nomor penerima dinormalisasi ke format Indonesia `62...`. Nomor lengkap disimpan menggunakan encrypted cast; hash dan empat digit terakhir tersedia untuk pencarian/audit dan tampilan masked.
- Kegagalan pembuatan outbox dicatat tanpa menggagalkan proses tiket utama.
- Pengaturan environment menjadi default. Tabel `whatsapp_notification_settings` menyediakan override global dan per-event dari UI admin.
- Halaman `/admin/whatsapp` menyediakan status kesiapan, profil perangkat, kontrol runtime, statistik, template, riwayat, retry, dan pesan uji. Akses memerlukan permission `notification.whatsapp.manage`.

## 4. File implementasi

### File baru

- `backend/config/whatsapp.php`
- `backend/app/Console/Commands/TestWhatsAppNotificationCommand.php`
- `backend/app/Contracts/WhatsAppGateway.php`
- `backend/app/Http/Controllers/Api/WhatsAppWebhookController.php`
- `backend/app/Http/Controllers/Api/V1/WhatsAppManagementController.php`
- `backend/app/Jobs/SendWhatsAppNotificationJob.php`
- `backend/app/Listeners/WhatsAppNotificationSubscriber.php`
- `backend/app/Models/WhatsAppNotification.php`
- `backend/app/Models/WhatsAppNotificationSetting.php`
- `backend/app/Services/WhatsApp/FonnteWhatsAppGateway.php`
- `backend/app/Services/WhatsAppNotificationService.php`
- `backend/app/Services/WhatsAppNotificationSettings.php`
- `backend/database/migrations/2026_08_05_000001_create_whatsapp_notifications_table.php`
- `backend/database/migrations/2026_08_06_000001_create_whatsapp_notification_settings_table.php`
- `backend/tests/Feature/SendWhatsAppNotificationJobTest.php`
- `backend/tests/Feature/WhatsAppConfigurationTest.php`
- `backend/tests/Feature/WhatsAppManagementApiTest.php`
- `backend/tests/Feature/WhatsAppNotificationSubscriberTest.php`
- `backend/tests/Feature/WhatsAppOutboxTest.php`
- `backend/tests/Feature/WhatsAppWebhookControllerTest.php`
- `frontend/src/pages/admin/WhatsAppSettingsPage.tsx`
- `frontend/src/services/whatsAppService.ts`

### File yang diubah untuk integrasi

- `backend/.env.example`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/app/Services/TicketSlaEscalationService.php`
- `backend/config/permissions.php`
- `backend/routes/api.php`
- `backend/tests/Feature/TicketSlaEscalationTest.php`
- `frontend/src/App.tsx`
- `frontend/src/api/client.ts`
- `frontend/src/components/Layout.tsx`

### Dokumentasi yang diganti

- `docs/crm-whatsapp-notification-stage-18-implementation-report.md`
- `docs/crm-whatsapp-notification-setup.md`
- `docs/crm-whatsapp-message-templates.md`

Perubahan worktree lain di luar daftar integrasi di atas tidak dinyatakan sebagai bagian dari implementasi Fonnte.

## 5. Arsitektur dan alur data

1. Domain event ditangani `WhatsAppNotificationSubscriber`, atau scan SLA memanggil `WhatsAppNotificationService` secara langsung.
2. Service memeriksa pengaturan efektif, event toggle, nomor penerima, dan template.
3. Service membuat row idempoten pada `whatsapp_notifications`; dispatch job dilakukan melalui `DB::afterCommit()` ke queue `notifications`.
4. `SendWhatsAppNotificationJob` mengunci row, mengubah status, dan memanggil `WhatsAppGateway`.
5. `FonnteWhatsAppGateway` memanggil `/send`, `/validate`, atau `/device`, menyaring respons, meredaksi secret, serta mengklasifikasikan kegagalan dan retryability.
6. Job menyimpan provider message ID, request ID, respons aman, jumlah percobaan, dan status `queued`, `processing`, `pending`, `sent`, `failed`, `invalid`, atau `expired`.
7. Webhook status mencocokkan `id` Fonnte dan memperbarui status secara monotonic agar callback lama tidak menurunkan status final.
8. API admin membaca outbox dan pengaturan runtime. Retry manual hanya tersedia untuk `failed`, `invalid`, dan `expired`.

Database migration mencakup dua bagian yang sama-sama wajib:

- `whatsapp_notifications`: outbox, status provider, recipient terenkripsi, deduplikasi, kegagalan aman, dan timestamp lifecycle.
- `whatsapp_notification_settings`: override runtime global dan map event. Jika belum ada row override, nilai efektif berasal dari environment/config.

## 6. Event dan penerima

| Event efektif | Sumber | Penerima | Template |
| --- | --- | --- | --- |
| `ticket_created` | `TicketSubmitted` | Requester jika tracking URL tersedia | `ticket_created_requester` |
| `ticket_created` | `TicketSubmitted` | Nomor IT Support terkonfigurasi | `ticket_created_it_support` |
| `ticket_assigned` | `TicketAssigned` | PIC/assignee | `ticket_assigned_pic` |
| `important_status_changed` | Analysis started, revision requested, UAT assigned, UAT approved, ticket rejected, atau aksi requester publik ditolak | Requester jika tracking URL tersedia | `important_status_requester` |
| `ticket_completed` | `TicketClosed` | Requester jika tracking URL tersedia | `ticket_completed_requester` |
| `sla_warning` | Scan SLA level approaching atau critical | PIC jika nomor valid; jika tidak, fallback IT Support | `sla_recipient` |
| `sla_breached` | Scan SLA level breached | PIC jika nomor valid; jika tidak, fallback IT Support | `sla_recipient` |
| `test` | CLI atau halaman admin | Nomor IT Support terkonfigurasi | `test_it_support` |

Penolakan aksi publik dikelompokkan ke `important_status_changed`. Toggle terpisah `uat_rejected` dan `confirmation_rejected` tidak diekspos karena belum mempunyai producer event khusus.

Pemindaian inactivity tetap menghasilkan notifikasi aplikasi, bukan WhatsApp. Tidak ada event WhatsApp inactivity pada implementasi final.

## 7. Environment variables

Variabel yang dibaca langsung oleh implementasi:

```env
WHATSAPP_PROVIDER=fonnte
WHATSAPP_NOTIFICATION_ENABLED=false
FONNTE_BASE_URL=https://api.fonnte.com
FONNTE_TOKEN=
FONNTE_COUNTRY_CODE=62
FONNTE_CONNECT_ONLY=true
FONNTE_TIMEOUT=15
FONNTE_RETRY_TIMES=3
FONNTE_IT_SUPPORT_NUMBER=<nomor-tujuan-dari-secret-manager>
FONNTE_WEBHOOK_SECRET=

WHATSAPP_EVENT_TICKET_CREATED=true
WHATSAPP_EVENT_TICKET_ASSIGNED=true
WHATSAPP_EVENT_IMPORTANT_STATUS_CHANGED=true
WHATSAPP_EVENT_SLA_WARNING=true
WHATSAPP_EVENT_SLA_BREACHED=true
WHATSAPP_EVENT_TICKET_COMPLETED=true
```

Queue `notifications`, job tries `2`, dan backoff `60,300,900` saat ini merupakan nilai config tetap, bukan environment variables. Jangan menambahkan nama env lama seperti `WHATSAPP_DRIVER`, `WHATSAPP_ACCESS_TOKEN`, atau variabel Meta karena tidak dibaca implementasi.

## 8. Endpoint

Webhook publik yang ekuivalen:

- `POST /api/webhooks/fonnte/message-status`
- `POST /api/v1/webhooks/fonnte/message-status` (alias v1)

Keduanya mewajibkan JSON dan header `X-Fonnte-Webhook-Secret` yang nilainya sama dengan `FONNTE_WEBHOOK_SECRET`.

API admin berada di `/api/v1/admin/whatsapp/*` dan mencakup settings, device profile, history, retry, dan test message. API ini memerlukan autentikasi aktif dan permission `notification.whatsapp.manage`.

## 9. Hasil pengujian aktual yang telah diberikan

Hasil berikut adalah catatan eksekusi yang telah dilaporkan selama implementasi; angka tidak direka ulang dan tidak berarti ada pengiriman live:

- Migration pertama kali: `Nothing to migrate` karena migration sudah pernah diterapkan pada database pengujian tersebut.
- Baseline implementasi awal: **483 tests, 3167 assertions** dan targeted WhatsApp **19 tests, 66 assertions**, lulus. Hasil final audit yang lebih baru dicatat pada `docs/tahap-1-audit-stabilisasi-whatsapp-fonnte.md`.
- Laravel Pint: lulus.
- Frontend typecheck: lulus.
- Frontend build: lulus.
- Frontend format check: lulus.
- Tidak ada pengujian dengan token Fonnte live dan tidak ada bukti pesan benar-benar terkirim ke perangkat WhatsApp.

## 10. Risiko, keterbatasan, dan pekerjaan pending

- Integrasi live masih perlu token, perangkat Fonnte yang connected, kuota/paket aktif, dan uji kirim terkontrol.
- Dokumentasi resmi Fonnte yang ditinjau tidak menunjukkan signature/header secret webhook. Reverse proxy diperlukan bila dashboard tidak dapat menambahkan `X-Fonnte-Webhook-Secret`.
- Endpoint webhook menerima `timestamp` bila dikirim. Karena halaman resmi update-message-status yang ditinjau hanya mencantumkan `device`, `id`, `stateid`, `status`, dan `state`, callback tanpa timestamp tetap diterima dan backend mencatat `received_at` dari waktu server.
- Status Fonnte aktual harus dikonfirmasi cocok dengan allowlist APG: `pending`, `sent`, `invalid`, `failed`, `expired`.
- Penolakan UAT dan konfirmasi tetap menggunakan event `important_status_changed`; toggle tanpa producer tidak diekspos.
- Runtime override database mengambil prioritas atas environment setelah disimpan. Deployment harus memeriksa UI/row database, bukan hanya `.env`.
- `FONNTE_CONNECT_ONLY=true` menolak kirim saat perangkat disconnected; outbox dapat berakhir retry/failure dan memerlukan pemantauan atau retry manual.
- Retensi outbox, alert operasional untuk failure/queue backlog, dan rotasi webhook secret/token perlu ditetapkan sebagai prosedur produksi.
- Validasi live webhook melalui proxy dan pengujian end-to-end belum dilakukan.

## 11. Deployment ringkas

1. Buat backup database dan deployment rollback point.
2. Hubungkan perangkat dan ambil token sesuai runbook tanpa memasukkan token ke Git.
3. Isi seluruh env yang diperlukan, tetapi pertahankan `WHATSAPP_NOTIFICATION_ENABLED=false` selama setup.
4. Jalankan `php artisan migrate --force` untuk outbox dan runtime settings migration.
5. Jalankan `php artisan optimize:clear` lalu cache config sesuai standar deployment proyek.
6. Jalankan worker dengan `php artisan queue:work --queue=notifications,default` melalui process supervisor.
7. Jalankan scheduler Laravel secara kontinu atau melalui cron `schedule:run` setiap menit.
8. Konfigurasi URL webhook/proxy, header secret, TLS, dan pembatasan trafik.
9. Verifikasi profil perangkat, queue, scheduler, permission admin, serta pengaturan runtime.
10. Aktifkan notifikasi di environment atau UI, lakukan satu uji terkontrol, dan pantau outbox serta log.

Detail lengkap dan prosedur rollback ada di `docs/crm-whatsapp-notification-setup.md`.

## 12. Kesiapan commit

## 12.1 Validasi live Fonnte

Validasi live dilakukan pada 7 Agustus 2026 tanpa menampilkan token atau nomor lengkap:

- Device Profile berhasil dan device berstatus `connect`.
- Nomor IT Support berhasil dinormalisasi dan terdaftar di WhatsApp menurut endpoint `/validate`.
- Request pengiriman pertama ditolak dengan `invalid/empty body value`, yang mengungkap bahwa Fonnte memerlukan multipart form-data, bukan JSON.
- Gateway diperbaiki menggunakan Laravel HTTP Client `asMultipart()` dan ditambahkan assertion test multipart.
- Retry tunggal diterima Fonnte dengan `status: true`, `process: pending`, detail `success! message in queue`, serta message ID dan request ID yang tersimpan di outbox.
- Runtime notification dikembalikan ke nonaktif dan queue `notifications` kosong setelah pengujian.
- Status delivery final masih menunggu webhook Fonnte.
- `FONNTE_WEBHOOK_SECRET` belum dikonfigurasi saat validasi sehingga callback status belum dapat diverifikasi end-to-end.

Implementasi Fonnte siap direview untuk commit: diff check, secret scan, migration, full test, formatter, typecheck, dan build telah lulus. Belum ada commit atau push. Saat staging, pastikan hanya file fitur ini yang dipilih karena worktree berisi beberapa perubahan line-ending frontend yang sudah ada sebelum pekerjaan ini.
