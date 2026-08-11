# Runbook Deployment Notifikasi WhatsApp Fonnte

Dokumen ini berfokus pada setup, deployment, verifikasi, operasi, dan rollback integrasi Fonnte. Laporan audit berada di `docs/crm-whatsapp-notification-stage-18-implementation-report.md`.

## 1. Prasyarat

- Akun Fonnte dengan perangkat dan kuota/paket yang sesuai.
- Nomor WhatsApp pengirim yang dapat dipindai melalui Linked Devices/WhatsApp Web.
- HTTPS publik untuk callback webhook atau reverse proxy.
- Queue backend menggunakan driver asynchronous di staging/production.
- Process supervisor untuk worker dan scheduler Laravel.
- Akses environment/secret manager dan akses admin dengan permission `notification.whatsapp.manage`.

Dokumentasi resmi:

- Dashboard device: <https://docs.fonnte.com/device/>
- Token API: <https://docs.fonnte.com/token-api-key/>
- Send API: <https://docs.fonnte.com/api-send-message/>
- Device profile API: <https://docs.fonnte.com/api-device-profile/>
- Message-status webhook: <https://docs.fonnte.com/webhook-update-message-status/>

## 2. Hubungkan perangkat Fonnte

1. Masuk ke dashboard Fonnte di <https://md.fonnte.com/>.
2. Buka menu **Device** dan pilih **Add Device** bila perangkat belum dibuat.
3. Isi nama dan nomor identifikasi perangkat sesuai kebijakan operasional.
4. Pilih aksi **Connect** untuk menampilkan QR code.
5. Pada WhatsApp pengirim, buka **Linked Devices**, pilih untuk menautkan perangkat, lalu pindai QR code.
6. Pastikan dashboard menampilkan status `connect`.
7. Pilih aksi **Token** pada device dan simpan token langsung ke secret manager. Jangan masukkan token ke dokumen, tiket, screenshot, chat, atau Git.
8. Verifikasi masa aktif paket dan kuota sebelum mengaktifkan APG.

Jika perangkat diganti atau token diduga bocor, reset/reconnect perangkat sesuai dashboard, rotasi token, perbarui secret manager, bersihkan config cache, dan restart worker.

## 3. Environment

Set nilai berikut pada backend. Contoh tidak berisi token atau webhook secret nyata.

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

Catatan:

- `FONNTE_TOKEN` harus berasal dari device yang telah connected.
- `FONNTE_WEBHOOK_SECRET` harus berupa random secret kuat dan berbeda dari token Fonnte.
- `FONNTE_IT_SUPPORT_NUMBER` adalah recipient untuk tiket baru internal, fallback SLA, dan pesan uji; ini bukan nomor identitas API.
- `FONNTE_CONNECT_ONLY=true` membuat Fonnte menolak request saat device disconnected. Nilai `false` mengizinkan Fonnte menahan request sampai device kembali connected dan harus menjadi keputusan operasional eksplisit.
- Toggle event di atas adalah default config. Setelah pengaturan disimpan dari UI, row `whatsapp_notification_settings` menjadi override runtime.
- Queue `notifications`, dua job attempts, dan backoff `60,300,900` bukan env; nilainya tetap di `backend/config/whatsapp.php`.

Setelah env berubah:

```bash
php artisan optimize:clear
php artisan config:cache
```

Restart semua queue worker setelah config cache diperbarui.

## 4. Database

Jalankan migration sebelum worker menerima job:

```bash
php artisan migrate --force
```

Migration wajib:

- `2026_08_05_000001_create_whatsapp_notifications_table.php` membuat outbox dan status pengiriman.
- `2026_08_06_000001_create_whatsapp_notification_settings_table.php` membuat global runtime setting dan event overrides.

Periksa `php artisan migrate:status` pada setiap target deployment; jangan menganggap semua environment sudah sama. Fresh migration serta rollback dan reapply kedua migration telah diverifikasi pada SQLite disposable. Verifikasi MySQL tetap menjadi CI gate sebelum release candidate.

## 5. Queue worker dan scheduler

Jalankan worker yang memprioritaskan queue notifikasi tetapi tetap menangani queue default:

```bash
php artisan queue:work --queue=notifications,default
```

Kelola command tersebut dengan Supervisor, systemd, container orchestrator, atau process manager standar deployment. Pastikan proses otomatis restart dan menerima restart setelah release.

Scheduler diperlukan karena scan SLA berjalan setiap 15 menit. Pilih salah satu pola berikut.

Worker scheduler kontinu:

```bash
php artisan schedule:work
```

Atau cron setiap menit dari directory backend:

```cron
* * * * * php artisan schedule:run >> /dev/null 2>&1
```

Jadwal existing juga menjalankan scan inactivity dan cleanup lain. Inactivity saat ini tidak menghasilkan WhatsApp; integrasi WhatsApp dari scheduler berasal dari `tickets:scan-sla-alerts`.

## 6. Webhook status

Endpoint callback APG utama:

```text
https://<backend-domain>/api/webhooks/fonnte/message-status
```

Alias versi yang setara:

```text
https://<backend-domain>/api/v1/webhooks/fonnte/message-status
```

Gunakan satu URL saja pada konfigurasi provider/proxy. Request harus berupa `POST` JSON dengan field `id`, `status`, `state`, `stateid`, dan `device`. Field `timestamp` bersifat opsional. Request wajib menyertakan header:

```http
X-Fonnte-Webhook-Secret: <nilai FONNTE_WEBHOOK_SECRET>
```

### Caveat header secret

Dokumentasi resmi Fonnte untuk update message status menunjukkan payload webhook, tetapi tidak menunjukkan signature verification atau kemampuan memasang custom request header. Dokumentasi dashboard device juga tidak boleh ditafsirkan sebagai dukungan header kustom.

APG mewajibkan secret melalui header. Lakukan salah satu berikut:

1. Jika dashboard Fonnte yang digunakan benar-benar menyediakan custom header, konfigurasikan header tersebut dan verifikasi dengan request capture di staging.
2. Jika dashboard tidak menyediakan custom header, gunakan reverse proxy/API gateway milik APG. Proxy menerima callback, menerapkan TLS, rate/IP controls bila data provider tersedia, memvalidasi dan membatasi body, menyuntikkan `X-Fonnte-Webhook-Secret`, lalu meneruskan request ke endpoint backend yang tidak diekspos langsung.

Jangan menaruh secret pada query string. Jangan menonaktifkan pengecekan header pada backend hanya agar callback lolos.

Payload resmi yang ditinjau tidak mencantumkan `timestamp`. Backend menerima field tersebut bila tersedia dan selalu mencatat `received_at` dari waktu server.

## 7. Urutan deployment aman

1. Backup database dan siapkan rollback release.
2. Deploy kode dengan `WHATSAPP_NOTIFICATION_ENABLED=false`.
3. Jalankan dependency install/build sesuai pipeline proyek.
4. Jalankan `php artisan migrate --force`.
5. Isi token, recipient, dan webhook secret melalui secret manager.
6. Jalankan `php artisan optimize:clear` dan `php artisan config:cache`.
7. Start/restart worker `php artisan queue:work --queue=notifications,default`.
8. Pastikan scheduler aktif dan `php artisan schedule:list` menampilkan scan SLA.
9. Konfigurasi webhook langsung atau reverse proxy dengan caveat header di atas.
10. Masuk ke `/admin/whatsapp`, periksa token/webhook readiness dan klik **Uji Koneksi** untuk membaca profil device.
11. Aktifkan notifikasi melalui environment atau kontrol runtime admin.
12. Kirim satu pesan uji, jalankan worker, lalu periksa status outbox dan penerimaan pada nomor tujuan.
13. Pantau failed jobs, backlog queue, log backend, status device, kuota Fonnte, dan callback webhook.

## 8. Uji koneksi dan pesan

### Uji profil perangkat

Di UI, buka `/admin/whatsapp` lalu pilih **Uji Koneksi**. Operasi ini memanggil endpoint device Fonnte dan tidak menampilkan token. Hasil yang diharapkan adalah request sukses dan `device_status` bernilai `connect`.

Uji langsung dari backend dapat dilakukan melalui endpoint admin terautentikasi `GET /api/v1/admin/whatsapp/device-profile`; gunakan UI kecuali sedang melakukan diagnosis API.

### Uji pesan CLI

Aktifkan notifikasi dan pastikan worker berjalan, lalu dari directory backend jalankan:

```bash
php artisan crm:whatsapp-test --note="Uji koneksi staging"
```

Command hanya diizinkan otomatis pada environment `local` atau `testing`. Untuk staging yang memang telah disetujui:

```bash
php artisan crm:whatsapp-test --force --note="Uji koneksi staging"
```

Command sukses hanya membuktikan outbox berhasil dibuat. Konfirmasi berikut secara terpisah:

- Worker mengambil job dari queue `notifications`.
- Row berpindah dari `queued`/`processing` ke `pending` atau `sent`.
- Provider message ID tersimpan.
- Webhook memperbarui status final.
- Pesan diterima oleh nomor tujuan yang telah disetujui.

Alternatif UI adalah tombol **Kirim Pesan Uji** pada `/admin/whatsapp`.

Live flow Fonnte sebelumnya telah menghasilkan callback delivery pada environment pengujian. Setiap environment target tetap harus diverifikasi ulang secara terkontrol sebelum release tanpa menyimpan credential atau nomor tujuan pada repository.

## 9. Operasi dan rollback

Emergency stop dapat dilakukan dari kontrol runtime `/admin/whatsapp`. Environment `WHATSAPP_NOTIFICATION_ENABLED=false` merupakan hard kill switch dan tidak dapat diaktifkan kembali oleh database override.

Untuk rollback operasional:

1. Nonaktifkan runtime setting dari UI/API.
2. Hentikan atau restart worker bila ada release/config issue; jangan menghapus job atau outbox tanpa analisis.
3. Periksa row `queued`, `pending`, dan `failed` serta Laravel failed jobs.
4. Putuskan apakah pesan aman untuk retry; UI hanya mengizinkan retry status `failed`, `invalid`, atau `expired`.
5. Jika token bocor, rotasi token di Fonnte dan secret manager sebelum mengaktifkan kembali.
6. Jika webhook secret bocor, rotasi secret pada proxy/backend secara terkoordinasi.

Rollback migration bersifat destruktif terhadap riwayat WhatsApp dan runtime setting. Jangan menjalankan rollback database tanpa backup dan persetujuan eksplisit.

## 10. Checklist sebelum commit/release

- Review diff final seluruh worktree dan staging area.
- Jalankan secret scan; pastikan tidak ada `FONNTE_TOKEN`, webhook secret, atau credential nyata.
- Pastikan file `.env` tidak di-stage.
- Pastikan migration status, backend tests, Pint, frontend typecheck/build/format masih lulus.
- Pastikan process definition memakai `--queue=notifications,default` dan scheduler aktif.
- Pastikan desain reverse proxy/header sudah diputuskan dan diuji.
- Pastikan live test, bila dilakukan, menggunakan recipient yang disetujui dan hasilnya dicatat tanpa secret.

Kesiapan commit tetap pending sampai diff final dan secret scan selesai.
