# Tahap 3A - Staging Infrastructure dan Environment Readiness APG CRM

Tanggal: 11 Agustus 2026
Project: APG CRM
RC version: `v1.0.0-rc.1`
Release branch: `release/v1.0.0-rc1`
Baseline commit: `6a04506a9b35656356aa4bafb194ac12219c65fa`
Status akhir: **NOT READY FOR STAGING DEPLOYMENT**

## Executive Summary

APG CRM RC1 secara kode telah memiliki komponen yang diperlukan untuk deployment staging manual:

- React/Vite production build.
- Laravel API dengan PHP 8.3+.
- MySQL persistence dan migration.
- Laravel Sanctum cookie authentication.
- Database queue dan failed-job persistence.
- Laravel scheduler dengan lima scheduled task.
- Private attachment storage abstraction.
- Database-aware health endpoint.
- Request ID dan security headers.
- Fonnte outbox, queue job, webhook, dan admin monitoring UI.
- Runbook deployment, rollback, backup, dan monitoring.

Baseline RC1 juga telah lulus CI untuk SQLite, MySQL 8.4, Laravel Pint, Composer audit, frontend typecheck, formatting, build, dan dependency audit.

Namun, staging belum siap dideploy karena repository belum memiliki atau belum menerima keputusan final mengenai:

- Target host/runtime staging.
- Domain dan sertifikat HTTPS.
- Reverse proxy final.
- Secret manager dan mekanisme secret delivery.
- MySQL staging instance.
- Durable private attachment storage.
- Queue dan scheduler process supervision.
- Central logging dan monitoring destination.
- Backup destination dan restore drill plan.
- Owner deployment, database, storage, queue, scheduler, dan incident response.
- Desain autentikasi callback Fonnte bila provider tidak mendukung custom header.

Tahap 3A tidak melakukan deployment, live Fonnte send, merge PR #2, perubahan production, atau perubahan terhadap baseline RC1.

## 1. Staging Architecture

### Arsitektur yang Direkomendasikan

Gunakan satu browser-visible HTTPS origin. Pola ini paling sederhana dan aman untuk Laravel Sanctum karena frontend, API, dan endpoint CSRF tetap first-party.

```text
Internet / Staging Testers
            |
            | HTTPS
            v
+---------------------------------------------+
| Reverse Proxy / HTTPS Edge                  |
| Host: crm-staging.apg.co.id                 |
|                                             |
| /assets/*, SPA routes -> React static files |
| /api/*               -> Laravel API         |
| /sanctum/*           -> Laravel API         |
| Fonnte webhook       -> controlled gateway  |
+----------------------+----------------------+
                       |
                       v
+---------------------------------------------+
| Laravel PHP Runtime                         |
| Document root: backend/public               |
|                                             |
| - API and Sanctum                           |
| - Health/readiness                          |
| - Queue outbox                              |
| - Attachment authorization                  |
+-----------+--------------+------------------+
            |              |
            |              +--------------------------+
            v                                         v
+---------------------+                 +-----------------------------+
| MySQL 8.4           |                 | Durable Private Storage     |
|                     |                 | Outside release directory   |
| - CRM data          |                 | or private object storage   |
| - sessions          |                 +-----------------------------+
| - cache/locks       |
| - jobs/failed jobs  |
+---------------------+

Laravel Runtime Processes
  |
  +-- Web/PHP process
  +-- Queue worker: notifications,default
  +-- Scheduler: schedule:run every minute
  +-- Central logging / monitoring agent

WhatsApp Flow

Laravel Outbox
   |
   v
Queue Worker
   |
   v
Fonnte API
   |
   v
WhatsApp Recipient
   |
   v
Fonnte Callback
   |
   v
Webhook Gateway / Edge Validation
   |
   | inject X-Fonnte-Webhook-Secret
   v
Laravel Webhook Endpoint
```

### Domain Recommendation

Preferred topology:

```text
https://crm-staging.apg.co.id/
https://crm-staging.apg.co.id/api/*
https://crm-staging.apg.co.id/sanctum/csrf-cookie
```

Canonical Fonnte callback:

```text
https://crm-staging.apg.co.id/api/webhooks/fonnte/message-status
```

Keuntungan satu origin:

- Cookie session menjadi first-party dan host-only.
- Tidak memerlukan cross-subdomain cookie.
- `SameSite=Lax` memadai.
- Tidak bergantung pada third-party cookie.
- Tidak membutuhkan browser-visible API subdomain.
- CORS dan Sanctum stateful-domain lebih sederhana.
- Satu domain dan satu sertifikat publik.

Jangan menggunakan Vite development server sebagai staging web server. `frontend/dist` harus disajikan sebagai static artifact.

## 2. Required Infrastructure

Komponen minimum yang benar-benar dibutuhkan:

### Edge dan Web

- Satu domain staging yang disetujui.
- Sertifikat TLS valid.
- HTTP ke HTTPS redirect.
- Reverse proxy atau ingress.
- Static hosting untuk `frontend/dist`.
- SPA routing fallback ke `index.html`.
- Laravel upstream yang hanya dapat dijangkau melalui edge/internal network.
- Request body limit yang memadai untuk attachment maksimum 10 MB ditambah multipart overhead.

### Application Runtime

- PHP 8.3 atau lebih baru.
- Ekstensi PDO MySQL.
- Composer 2 saat build/install.
- Web server/PHP process manager.
- Document root `backend/public`, bukan repository root.
- Writable runtime paths untuk Laravel cache dan logs bila tidak memakai stderr.
- Immutable checkout/artifact dari commit `6a04506`.

### Database

- MySQL 8.4 direkomendasikan agar sama dengan CI.
- Dedicated staging database.
- Dedicated least-privilege database user.
- Durable database volume/service.
- Backup destination terpisah dari application host.

### Process Supervision

- Queue worker supervisor untuk `notifications,default`.
- Scheduler cron atau supervised `schedule:work`.
- Automatic restart setelah process crash.
- Graceful restart saat release/config berubah.
- Process heartbeat dan failure alerting.

### Storage

- Durable private attachment path di luar release directory, atau private object storage.
- Service account permissions terbatas.
- Storage capacity monitoring.
- Snapshot/versioning atau backup mechanism.

### External Services

- Secret manager atau protected platform environment.
- Sandbox SMTP untuk OTP/public-history UAT.
- Fonnte staging/test credential dan approved staging recipient.
- Central log collector atau platform stderr collection.
- Monitoring/alert destination.

### Tidak Wajib untuk Staging Awal

- Redis tidak wajib; database cache, sessions, locks, dan queue dapat digunakan.
- Containerization tidak wajib jika platform PHP dan process definitions reproducible.
- `php artisan storage:link` tidak diperlukan untuk private ticket attachments.

## 3. Required Environment Variables

Semua nilai secret harus berasal dari secret manager atau protected platform environment. Jangan menaruh secret pada repository, frontend `VITE_*`, command line history, atau deployment logs.

### Application

```env
APP_NAME="Tic Hub API"
APP_ENV=staging
APP_KEY=<secret>
APP_DEBUG=false
APP_URL=https://crm-staging.apg.co.id
APP_TIMEZONE=Asia/Jakarta
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US
BCRYPT_ROUNDS=12
```

Ketentuan:

- `APP_KEY` dibuat satu kali dan dipertahankan sepanjang lifecycle staging.
- Jangan generate ulang `APP_KEY` pada setiap deployment karena akan merusak encrypted data.
- `APP_DEBUG` harus `false`.

### Frontend Build

```env
VITE_API_BASE_URL=/api/v1
VITE_BACKEND_URL=
VITE_BACKEND_PROXY_TARGET=
VITE_ALLOWED_HOSTS=
VITE_TINYMCE_API_KEY=<browser-visible-key-or-empty>
```

Ketentuan:

- Nilai `VITE_*` masuk ke browser bundle dan tidak boleh berisi secret.
- `VITE_API_BASE_URL=/api/v1` menggunakan same-origin edge proxy.
- `VITE_BACKEND_PROXY_TARGET` hanya untuk Vite development server dan tidak digunakan sebagai staging reverse proxy.
- Generated frontend assets harus diperiksa agar tidak mengandung `localhost` backend URL.

### Frontend, Sanctum, dan Proxy

```env
FRONTEND_URL=https://crm-staging.apg.co.id
SANCTUM_STATEFUL_DOMAINS=crm-staging.apg.co.id
TRUSTED_PROXIES=<exact-private-edge-ip-or-cidr>
```

Ketentuan:

- `FRONTEND_URL` menggunakan full HTTPS origin tanpa path.
- `SANCTUM_STATEFUL_DOMAINS` menggunakan hostname tanpa scheme.
- Jangan menggunakan wildcard.
- `TRUSTED_PROXIES` harus alamat atau CIDR edge yang sebenarnya, bukan universal wildcard.

### Database

```env
DB_CONNECTION=mysql
DB_HOST=<private-mysql-host>
DB_PORT=3306
DB_DATABASE=<staging-database-name>
DB_USERNAME=<least-privilege-staging-user>
DB_PASSWORD=<secret>
```

### Session

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_COOKIE=apg_crm_staging_session
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_PARTITIONED_COOKIE=false
```

Ketentuan:

- `SESSION_DOMAIN` dikosongkan agar cookie host-only.
- Cookie session wajib `Secure` dan `HttpOnly`.
- `SameSite=Lax` sesuai topology satu origin.

### Cache dan Distributed Locks

```env
CACHE_STORE=database
CACHE_PREFIX=apg_crm_staging_
DB_CACHE_CONNECTION=mysql
DB_CACHE_TABLE=cache
DB_CACHE_LOCK_CONNECTION=mysql
DB_CACHE_LOCK_TABLE=cache_locks
```

Database cache dapat digunakan untuk `onOneServer()` dan `withoutOverlapping()` selama semua app instance memakai database/cache yang sama.

### Queue

```env
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=mysql
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90
QUEUE_FAILED_DRIVER=database-uuids
```

Queue WhatsApp sendiri bernama `notifications` dan ditentukan oleh config aplikasi. Worker wajib mendengarkan `notifications,default`.

### Storage dan Attachment

Opsi durable local storage:

```env
FILESYSTEM_DISK=local
TICKET_ATTACHMENT_DISK=ticket_attachments
TICKET_ATTACHMENT_ROOT=/srv/apg-crm-staging/private-ticket-attachments
PUBLIC_STORAGE_ROOT=/srv/apg-crm-staging/legacy-public-storage
```

Penting:

- `TICKET_ATTACHMENT_ROOT` hanya digunakan oleh disk `ticket_attachments`.
- Jika `TICKET_ATTACHMENT_DISK=local`, external `TICKET_ATTACHMENT_ROOT` tidak digunakan.
- Path private harus berada di luar immutable release directory.
- Jangan menjalankan `storage:link` untuk private ticket evidence.

Opsi private S3-compatible storage memerlukan:

```env
TICKET_ATTACHMENT_DISK=s3
AWS_ACCESS_KEY_ID=<secret>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=<region>
AWS_BUCKET=<private-staging-bucket>
AWS_URL=
AWS_ENDPOINT=<optional-private-endpoint>
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Bucket policy, encryption, versioning, lifecycle, dan restore procedure harus ditentukan oleh infrastructure owner.

### Public Tracking dan OTP

```env
PUBLIC_TICKET_TRACKING_EXPIRY_DAYS=<approved-positive-integer>
PUBLIC_TICKET_TRACKING_KEY=<secret>
PUBLIC_TICKET_TRACKING_KEYS=<optional-versioned-key-ring>
PUBLIC_TICKET_TRACKING_KEY_VERSION=1

PUBLIC_HISTORY_DRIVER=mail
PUBLIC_HISTORY_OTP_EXPIRY_MINUTES=10
PUBLIC_HISTORY_MAX_ATTEMPTS=5
PUBLIC_HISTORY_RESEND_COOLDOWN_SECONDS=60
PUBLIC_HISTORY_ACCESS_TTL_MINUTES=15
PUBLIC_ACTION_ACCESS_TTL_MINUTES=10
PUBLIC_HISTORY_IDENTITY_KEY=<secret-at-least-32-bytes>
PUBLIC_HISTORY_OTP_PEPPER=<independent-secret-at-least-32-bytes>
```

Gunakan secret staging yang berbeda dari development dan production.

### Mail

```env
MAIL_MAILER=smtp
MAIL_SCHEME=<tls-or-null-per-provider>
MAIL_HOST=<staging-smtp-host>
MAIL_PORT=<smtp-port>
MAIL_USERNAME=<secret>
MAIL_PASSWORD=<secret>
MAIL_FROM_ADDRESS=<approved-staging-address>
MAIL_FROM_NAME="${APP_NAME}"
```

Gunakan sandbox SMTP bila public-history OTP masuk scope UAT.

### Logging dan Monitoring

Recommended platform logging:

```env
LOG_CHANNEL=stderr
LOG_LEVEL=info
LOG_DEPRECATIONS_CHANNEL=null
```

Alternatif local daily rotation:

```env
LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=info
LOG_DAILY_DAYS=14
```

Local rotation tetap membutuhkan log collector dan durable writable path.

### Dynamic Workflow

```env
CRM_DYNAMIC_WORKFLOW_ENABLED=false
```

Sebelum diaktifkan:

```bash
php artisan crm:workflow-check
```

Aktifkan hanya setelah active published workflow tersedia dan hasil check lulus.

### Fonnte

Initial deployment harus tetap disabled:

```env
WHATSAPP_PROVIDER=fonnte
WHATSAPP_NOTIFICATION_ENABLED=false
WHATSAPP_EVENT_TICKET_CREATED=false
WHATSAPP_EVENT_TICKET_ASSIGNED=false
WHATSAPP_EVENT_IMPORTANT_STATUS_CHANGED=false
WHATSAPP_EVENT_SLA_WARNING=false
WHATSAPP_EVENT_SLA_BREACHED=false
WHATSAPP_EVENT_TICKET_COMPLETED=false
WHATSAPP_RECIPIENT_TICKET_CREATED_IT_SUPPORT=false

FONNTE_BASE_URL=https://api.fonnte.com
FONNTE_TOKEN=<secret>
FONNTE_COUNTRY_CODE=62
FONNTE_CONNECT_ONLY=true
FONNTE_TIMEOUT=15
FONNTE_RETRY_TIMES=1
FONNTE_IT_SUPPORT_NUMBER=<approved-staging-recipient>
FONNTE_WEBHOOK_SECRET=<independent-random-secret-at-least-32-bytes>
```

Aktifkan environment kill switch dan event satu per satu hanya setelah queue, webhook, monitoring, dan approved recipient siap.

## 4. Database Readiness

### Recommended Configuration

```text
Engine: MySQL
Version: 8.4 preferred
Charset: utf8mb4
Collation: utf8mb4_unicode_ci or platform-approved compatible collation
Database: dedicated staging database
User: dedicated least-privilege application user
Storage: durable and backed up
```

### Evidence yang Sudah Ada

CI baseline `6a04506` menggunakan MySQL 8.4 dan telah lulus:

- Fresh migration dan seed.
- Rollback migration terakhir.
- Reapply migration.
- Migration status.
- Fresh migration ulang.
- Full backend test suite pada MySQL.
- Schema WhatsApp dan unique provider message correlation.

### Staging Procedure

Untuk empty persistent staging database:

```bash
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
php artisan migrate:status
```

Jangan menggunakan:

```bash
php artisan migrate:fresh
```

pada retained staging database.

Provision named staging administrator melalui protected environment:

```bash
TIC_HUB_BOOTSTRAP_PASSWORD=<secret> php artisan users:provision-testing \
  --name="<accountable-operator>" \
  --email="<named-staging-email>" \
  --role=admin
```

Command mendukung staging, menolak production, tidak mencetak password, dan tidak menimpa existing user.

### Database Backup

Pre-deployment dump tanpa password di command line:

```bash
mysqldump \
  --defaults-extra-file=<protected-client-config> \
  --single-transaction \
  --routines \
  --triggers \
  --set-gtid-purged=OFF \
  <staging-database> | gzip > <backup-file>.sql.gz
```

Target awal pada runbook, masih memerlukan approval:

- RPO: 24 jam.
- RTO: 4 jam.
- Retention: 14 daily, 8 weekly, 12 monthly.

### Database Gaps

- Staging MySQL instance belum dipilih/provisioned.
- Backup destination belum dipilih.
- Restore drill belum dijalankan.
- Database/restore owner masih belum ditetapkan.
- CI hanya membuktikan disposable schema; staging topology dan retained-data behavior tetap perlu evidence.

## 5. Queue dan Scheduler Readiness

### Queue

WhatsApp menggunakan real asynchronous job:

```text
SendWhatsAppNotificationJob implements ShouldQueue
```

Queue yang harus didengarkan:

```text
notifications
default
```

Recommended supervised worker:

```bash
php artisan queue:work database \
  --queue=notifications,default \
  --sleep=3 \
  --tries=3 \
  --timeout=90 \
  --max-time=3600
```

Operational commands:

```bash
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry <failed-job-id>
```

Catatan:

- Job WhatsApp memiliki maksimum dua attempt sendiri.
- Job timeout mengikuti Fonnte timeout plus safety margin, default sekitar 20 detik.
- Provider timeout ambigu tidak diulang otomatis untuk menghindari duplicate message.
- Manual retry wajib setelah root-cause dan provider-state review.
- Generic `docs/scheduler-queue-runbook.md` tertinggal karena masih menyatakan persistent worker belum diperlukan.
- `docs/crm-whatsapp-notification-setup.md` dan source code adalah source of truth terbaru.

### Scheduler

Lima scheduled task aktif:

```text
tickets:scan-sla-alerts     every 15 minutes
tickets:scan-inactivity     every 15 minutes
public-history:cleanup      hourly
public-actions:cleanup      hourly
idempotency:cleanup         daily
```

Semua menggunakan overlap/distributed lock sesuai definisi pada `backend/routes/console.php`.

Pilih tepat satu scheduler mechanism:

```cron
* * * * * cd <release>/backend && php artisan schedule:run >> /dev/null 2>&1
```

atau supervised:

```bash
php artisan schedule:work
```

Jangan menjalankan keduanya.

Verification:

```bash
php artisan schedule:list
php artisan tickets:scan-sla-alerts
php artisan tickets:scan-sla-alerts
php artisan tickets:scan-inactivity
php artisan tickets:scan-inactivity
```

Run kedua scanner harus membuktikan deduplication pada eligibility window yang sama.

### Queue dan Scheduler Blockers

- Belum ada Supervisor/systemd/container process definition.
- Belum ada process owner.
- Belum ada queue backlog/oldest-job monitor.
- Belum ada scheduler heartbeat monitor.
- Belum ada restart policy dan alert destination.

## 6. Sanctum, HTTPS, dan Reverse Proxy

### Required Proxy Routing

Route matching harus dilakukan sebelum SPA fallback:

```text
/api/*       -> Laravel upstream, URI tetap utuh
/sanctum/*   -> Laravel upstream, URI tetap utuh
/assets/*    -> exact static file
other routes -> frontend/dist/index.html
```

Jangan:

- Mengubah `/api/v1` menjadi `/v1`.
- Menangani API 401/404/419 menggunakan `index.html`.
- Menyajikan repository root atau `backend/` sebagai document root.
- Mengekspos Laravel upstream langsung ke internet bila proxy trust digunakan.

Conceptual proxy headers:

```nginx
proxy_set_header Host              $host;
proxy_set_header X-Forwarded-Host  $host;
proxy_set_header X-Forwarded-Proto https;
proxy_set_header X-Forwarded-Port  443;
proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
proxy_set_header X-Request-ID      $request_id;
```

Edge harus mengganti forwarded headers dari client, bukan mempercayainya langsung.

### Frontend Static Cache

```text
index.html: no-cache
hashed /assets files: public, max-age=31536000, immutable
```

### Session Requirements

- HTTPS wajib.
- `SESSION_SECURE_COOKIE=true`.
- `SESSION_HTTP_ONLY=true`.
- `SESSION_SAME_SITE=lax`.
- Host-only cookie dengan `SESSION_DOMAIN` kosong.
- Exact `FRONTEND_URL` dan `SANCTUM_STATEFUL_DOMAINS`.
- `/sanctum/csrf-cookie` harus diteruskan ke Laravel.
- Browser login, session restore, 419 refresh, logout, dan role authorization harus diuji.

### Security Headers

Laravel API sudah memberikan:

- `X-Content-Type-Options: nosniff`.
- `X-Frame-Options: DENY`.
- `Referrer-Policy`.
- `Permissions-Policy`.
- API-focused CSP.

Frontend document disajikan terpisah oleh edge dan membutuhkan minimal:

- HSTS setelah HTTPS terbukti benar.
- `X-Content-Type-Options: nosniff`.
- `X-Frame-Options: DENY`.
- `Referrer-Policy: no-referrer`.
- `Permissions-Policy`.
- Frontend CSP yang diuji, sebaiknya report-only sebelum enforce.

TinyMCE external resources perlu dipetakan sebelum CSP enforce.

### Reverse Proxy Blocker

Repository belum mempunyai complete staging reverse-proxy configuration. File Nginx yang tersedia hanya khusus validation/redaction dan bukan deployment topology lengkap. Final configuration dan evidence tetap wajib sebelum deploy.

## 7. Fonnte Readiness

### Implemented Flow

```text
Ticket/SLA Event
      |
      v
WhatsAppNotificationService
      |
      v
Database Outbox
      |
      v
Queue: notifications
      |
      v
SendWhatsAppNotificationJob
      |
      v
Fonnte API
      |
      v
WhatsApp Recipient
      |
      v
Fonnte Webhook
      |
      v
WhatsAppWebhookController
```

### Webhook Contract

Canonical endpoint:

```text
POST /api/webhooks/fonnte/message-status
```

Backend mewajibkan:

```http
X-Fonnte-Webhook-Secret: <secret>
```

Kontrol backend:

- Secret minimum 32 karakter.
- Constant-time comparison.
- JSON content type.
- Maximum body 64 KiB.
- Strict payload fields dan lengths.
- Provider-scoped correlation.
- Row lock dan terminal-state idempotency.
- Unknown correlation mengembalikan HTTP 503 untuk meminta retry.
- Route throttle 60 request per menit.

### Webhook Gateway Requirement

Kemampuan native Fonnte untuk mengirim custom header belum terbukti. Jika tidak tersedia, diperlukan dedicated gateway/edge rule yang:

1. Menerima hanya HTTPS POST pada exact path.
2. Membatasi body maksimum 64 KiB.
3. Memvalidasi JSON shape dan field lengths.
4. Menerapkan rate/burst limit.
5. Menggunakan official source IP allowlist jika Fonnte menyediakannya.
6. Menghapus externally supplied `X-Fonnte-Webhook-Secret`.
7. Menginjeksi secret hanya setelah edge controls lulus.
8. Meneruskan ke private Laravel upstream.
9. Meredaksi header, body, phone, message, dan tracking token dari logs.
10. Menghasilkan alert untuk auth failures dan unmatched-ID spikes.

Header injection saja bukan autentikasi kuat jika gateway dapat dipanggil siapa pun. Bila provider tidak menyediakan signature, custom header, atau stable source IP, residual callback-origin risk memerlukan security acceptance atau perubahan gateway/provider.

### Activation Sequence

1. Deploy dengan `WHATSAPP_NOTIFICATION_ENABLED=false`.
2. Jalankan dan monitor queue worker.
3. Konfigurasi credential melalui secret manager.
4. Uji device profile melalui admin API/UI.
5. Validasi webhook gateway tanpa live send.
6. Setelah approval terpisah, lakukan controlled live test ke approved staging recipient.
7. Verifikasi outbox, queue consumption, provider ID, callback, final status, dan no-secret logging.
8. Aktifkan event satu per satu.

Tahap 3A tidak melakukan live send.

## 8. Storage dan Backup

### Attachment Storage

Private attachment harus:

- Berada di luar release directory.
- Tidak dapat diakses sebagai public static URL.
- Hanya dibaca melalui authorization-gated Laravel endpoint.
- Bertahan setelah release replacement/restart.
- Memiliki least-privilege ownership dan permission.
- Memiliki capacity/error monitoring.
- Masuk backup/snapshot.

Recommended local path:

```text
/srv/apg-crm-staging/private-ticket-attachments
```

Tidak diperlukan symlink public untuk ticket evidence.

### Existing Attachment Controls

- Public disk ditolak untuk attachment baru.
- UUID stored filenames.
- Original filename sanitation.
- Executable filename rejection.
- MIME/type/size validation.
- Authorized download.
- Private/no-store response headers.
- Quarantine behavior saat deletion.
- Legacy attachment migration command dengan dry-run, checksum, dan rollback per ticket.

### Outstanding Storage Risk

Antivirus/malware scanner belum terintegrasi. Untuk controlled staging:

- Gunakan synthetic/non-sensitive test files.
- Batasi tester yang dapat mengunduh attachment.
- Catat accepted risk.
- Jangan menganggap extension/MIME checks sebagai malware detection.

### Backup Requirements

Backup harus mencakup:

- MySQL transactional dump/snapshot.
- Private attachment snapshot/version.
- SHA-256 checksum evidence.
- Release commit/tag.
- MySQL version dan migration status.
- Operator dan timestamp.
- Storage snapshot/version ID.

### Restore Drill

Restore harus dilakukan ke isolated database dan storage path:

```bash
gzip -dc <backup-file>.sql.gz | \
  mysql --defaults-extra-file=<protected-client-config> \
  <isolated-restore-database>
```

Lalu:

- Restore private evidence ke isolated path/bucket.
- Jalankan `php artisan migrate:status` tanpa apply migration.
- Verifikasi table counts dan critical samples.
- Cocokkan attachment metadata, file presence, size, dan sample checksum.
- Jalankan read-only authentication, ticket, report, dan evidence authorization smoke tests.
- Catat duration, achieved RPO/RTO, dan remediation.

Restore drill belum pernah menghasilkan staging evidence final dan merupakan blocker acceptance.

## 9. Monitoring

### Minimum Staging Monitoring

| Area | Signal minimum |
| --- | --- |
| Web | `/up` availability |
| API | `/api/health` 200/503 |
| HTTP | 5xx rate dan p95 latency |
| Auth | login throttling dan unusual 401/403 volume |
| Database | connectivity, connections, latency, storage, saturation |
| Queue | worker process, queue depth, oldest job, failed jobs |
| Scheduler | heartbeat, duration, exit code, missed runs |
| Storage | write/read failures, permissions, capacity |
| Backup | backup age, checksum status, restore-drill age |
| Fonnte | device state, quota, 429/5xx/timeout, failed statuses |
| Webhook | 401, validation 4xx, unmatched 503, callback latency |

### Fonnte-Specific Alerts

Alert minimal untuk:

- `INVALID_TOKEN`.
- `DEVICE_DISCONNECTED`.
- `QUOTA_EXCEEDED`.
- `DELIVERY_STATE_UNKNOWN`.
- `QUEUE_DISPATCH_FAILED`.
- `QUEUE_ATTEMPTS_EXHAUSTED`.
- Stale `processing`.
- Stale `pending` tanpa callback final.
- Sustained webhook auth failures.
- Sustained unmatched provider ID.

Jangan otomatis retry `DELIVERY_STATE_UNKNOWN`.

### Logging Policy

Log harus dapat dicari menggunakan `X-Request-ID` dan tidak boleh memuat:

- Password.
- Authorization headers.
- Session/CSRF cookies.
- Fonnte token.
- Webhook secret.
- Database DSN/password.
- Full phone number.
- Full message body.
- Tracking token.
- Attachment content.

### Monitoring Gap

Repository tidak memilih monitoring provider dan tidak memasang alert destination. Infrastructure owner harus memetakan signal di atas ke platform staging sebelum deployment approval.

## 10. Health Check

### Existing Endpoints

Liveness:

```text
GET /up
```

Database-connected readiness aliases:

```text
GET /api/health
GET /api/v1/health
```

`/api/health`:

- Membuka database connection.
- HTTP 200 jika database connected.
- HTTP 503 dengan `DATABASE_UNAVAILABLE` jika connection gagal.
- Menyertakan request ID melalui standard API envelope/header.
- Tidak mengembalikan exception detail atau secret.

### Limitations

Health endpoint tidak memeriksa:

- Migration currency.
- Queue worker heartbeat.
- Queue backlog.
- Scheduler heartbeat.
- Cache lock.
- Storage writability/capacity.
- Mail delivery.
- Fonnte connectivity.

Karena itu:

- `/up` digunakan sebagai liveness.
- `/api/health` digunakan sebagai application/database readiness.
- Queue, scheduler, storage, backup, SMTP, dan Fonnte harus dimonitor secara eksternal.

Tidak diperlukan perubahan health endpoint pada Tahap 3A.

## 11. Outstanding Blockers

### BLOCKER

1. **Target infrastructure belum dipilih**
   - Belum ada host/runtime, domain, certificate, reverse proxy, atau deployment mechanism final.

2. **Reverse proxy dan Sanctum topology belum dikonfigurasi**
   - `/api`, `/sanctum`, SPA fallback, forwarded headers, upload limit, static cache, dan frontend security headers belum memiliki config/evidence staging.

3. **Secret delivery belum ditentukan**
   - APP key, DB password, public tracking keys, OTP secrets, SMTP, dan Fonnte secrets membutuhkan approved secret manager/platform mechanism.

4. **MySQL staging instance belum tersedia**
   - CI MySQL 8.4 hijau, tetapi staging service, database name, account, network, backup, dan owner belum ada.

5. **Durable private attachment storage belum dipilih**
   - Default path dapat berada di release tree. Staging wajib memilih external private path atau private object storage.

6. **Queue worker belum memiliki supervised process definition**
   - Worker `notifications,default` mandatory untuk Fonnte. Belum ada supervisor/systemd/container policy dan heartbeat.

7. **Scheduler belum memiliki process definition dan heartbeat**
   - Lima scheduled tasks membutuhkan exactly-one scheduler mechanism dan monitor.

8. **Monitoring/logging destination belum dipilih**
   - Tidak ada central collector, alert destination, threshold, atau on-call owner.

9. **Backup target dan restore drill belum siap**
   - Belum ada database/storage backup destination dan isolated restore evidence.

10. **Fonnte webhook authentication topology belum terbukti**
    - Native custom-header support belum dikonfirmasi. Gateway/source validation dan bypass prevention belum didesain final.

11. **Operational owners belum ditetapkan**
    - Deployment, database, restore, storage, queue, scheduler, monitoring, incident, dan rollback approver masih TBD.

12. **Staging-safe configuration preflight belum ditentukan**
    - Strong startup guard hanya berjalan saat `APP_ENV=production`; staging membutuhkan external checklist/preflight yang memvalidasi debug, HTTPS, cookies, secrets, storage, dan Fonnte readiness.

### IMPORTANT

1. Generic queue runbook tertinggal dan tidak menyebut `notifications` queue.
2. Generic scheduler runbook hanya menyebut dua dari lima scheduled task.
3. `.env.example` dapat menyesatkan attachment root jika disk tetap `local`.
4. Frontend CSP belum dirancang dan diuji bersama TinyMCE.
5. Pending/ambiguous WhatsApp reconciliation belum otomatis.
6. SMTP sandbox belum dipilih untuk OTP UAT.
7. Artifact publishing tidak tersedia; staging harus build dari immutable checkout dan mencatat checksums.
8. Database rollback CI hanya membuktikan final migration pada disposable database.
9. Attachment malware scanning belum tersedia.
10. Retention/privacy policy untuk attachment dan WhatsApp outbox belum disetujui.
11. Performance dan all-role responsive UAT belum dijalankan pada staging.
12. Default GitHub branch divergen dari integration branch dan memerlukan keputusan terpisah, tanpa mengubah RC1.

### NON-BLOCKER

1. Redis belum digunakan; database-backed cache/session/queue cukup untuk initial staging.
2. Containerization belum tersedia; manual deployment tetap mungkin bila process definitions reproducible.
3. Health endpoint hanya database-aware; external monitors dapat melengkapi tanpa perubahan aplikasi.
4. `/api/health` mengembalikan environment name; risikonya rendah untuk staging.
5. Tidak ada `storage:link` untuk private attachments adalah perilaku yang benar.
6. Testing/staging user provisioning command sesuai untuk named UAT identities.
7. Release/tag push tidak memicu CI baru; baseline commit memiliki commit-specific green CI evidence.

## 12. Deployment Checklist untuk Tahap 3B

### Governance dan Baseline

- [ ] Konfirmasi version `v1.0.0-rc.1`.
- [ ] Konfirmasi release branch `release/v1.0.0-rc1`.
- [ ] Konfirmasi deployed commit tepat `6a04506a9b35656356aa4bafb194ac12219c65fa`.
- [ ] Catat CI run `31456221988` sebagai baseline evidence.
- [ ] Tetapkan deployment owner.
- [ ] Tetapkan database/restore owner.
- [ ] Tetapkan queue/scheduler owner.
- [ ] Tetapkan monitoring/on-call owner.
- [ ] Tetapkan rollback authority.

### Infrastructure

- [ ] Provision PHP 8.3+ staging runtime.
- [ ] Provision MySQL 8.4 staging service.
- [ ] Provision durable private attachment storage.
- [ ] Provision HTTPS domain dan certificate.
- [ ] Provision reverse proxy/ingress.
- [ ] Provision secret manager/protected environment delivery.
- [ ] Provision sandbox SMTP.
- [ ] Provision central logging/monitoring destination.
- [ ] Provision database dan attachment backup destination.

### Frontend Artifact

- [ ] Checkout immutable baseline `6a04506`.
- [ ] Jalankan `corepack pnpm install --frozen-lockfile`.
- [ ] Jalankan `corepack pnpm typecheck`.
- [ ] Jalankan `corepack pnpm format:check`.
- [ ] Jalankan `corepack pnpm build`.
- [ ] Catat checksum `frontend/dist` artifact.
- [ ] Pastikan bundle tidak mengandung `localhost` API URL.
- [ ] Publish static artifact.
- [ ] Konfigurasi SPA fallback tanpa menangkap `/api` atau `/sanctum`.

### Backend Artifact

- [ ] Checkout immutable baseline `6a04506`.
- [ ] Jalankan `composer validate --no-interaction`.
- [ ] Jalankan `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
- [ ] Jalankan `composer audit --locked --no-interaction`.
- [ ] Catat backend artifact/commit checksum.
- [ ] Set document root ke `backend/public`.
- [ ] Pastikan writable runtime directories sesuai platform.

### Environment Preflight

- [ ] `APP_ENV=staging`.
- [ ] `APP_DEBUG=false`.
- [ ] `APP_KEY` tersedia dan terlindungi.
- [ ] `APP_URL` dan `FRONTEND_URL` exact HTTPS origin.
- [ ] `SANCTUM_STATEFUL_DOMAINS` exact hostname.
- [ ] `TRUSTED_PROXIES` exact edge IP/CIDR.
- [ ] Secure/HttpOnly/SameSite cookie settings benar.
- [ ] MySQL connection menggunakan dedicated staging account.
- [ ] Database cache, session, queue, dan locks dikonfigurasi.
- [ ] Private attachment disk/root benar.
- [ ] Public tracking dan OTP secrets berbeda dari environment lain.
- [ ] SMTP sandbox tersedia.
- [ ] Logging menggunakan approved staging channel dan `info` level.
- [ ] WhatsApp tetap disabled pada initial deploy.

### Database

- [ ] Ambil pre-deployment database backup jika database retained.
- [ ] Ambil attachment snapshot jika storage retained.
- [ ] Verifikasi backup non-empty dan checksum.
- [ ] Jalankan `php artisan migrate --force`.
- [ ] Jalankan `php artisan migrate:status`.
- [ ] Seed canonical roles saja sesuai kebutuhan.
- [ ] Provision named staging admin dengan protected bootstrap password.
- [ ] Jangan menjalankan `migrate:fresh` pada retained staging.

### Laravel Cache dan Routes

- [ ] Jalankan `php artisan optimize:clear`.
- [ ] Jalankan `php artisan config:cache`.
- [ ] Jalankan `php artisan route:cache`.
- [ ] Jalankan `php artisan route:list` untuk evidence.
- [ ] Jalankan `php artisan schedule:list` untuk evidence.

### Reverse Proxy dan HTTPS

- [ ] HTTPS certificate valid.
- [ ] HTTP redirect ke HTTPS.
- [ ] `/api/*` diteruskan tanpa URI rewrite yang salah.
- [ ] `/sanctum/csrf-cookie` diteruskan ke Laravel.
- [ ] API error tidak berubah menjadi SPA HTML.
- [ ] SPA deep links bekerja.
- [ ] Forwarded host/proto/port benar.
- [ ] Spoofed forwarded headers dari client tidak dipercaya.
- [ ] Body upload limit memadai.
- [ ] Frontend static cache policy benar.
- [ ] API dan frontend security headers diverifikasi.
- [ ] Frontend CSP diuji report-only sebelum enforce.

### Queue dan Scheduler

- [ ] Deploy supervised worker `notifications,default`.
- [ ] Konfigurasi automatic restart dan graceful restart.
- [ ] Verifikasi `php artisan queue:failed`.
- [ ] Verifikasi queue depth dan oldest-job visibility.
- [ ] Deploy exactly-one scheduler mechanism.
- [ ] Verifikasi scheduler heartbeat.
- [ ] Jalankan SLA scanner dua kali dan cek deduplication.
- [ ] Jalankan inactivity scanner dua kali dan cek deduplication.
- [ ] Verifikasi hourly/daily cleanup tasks pada `schedule:list`.

### Storage

- [ ] Private attachment path/bucket berada di luar release tree.
- [ ] Service account permission least privilege.
- [ ] Public direct URL tidak tersedia.
- [ ] Upload/download authorization smoke test lulus.
- [ ] Cross-user access denial lulus.
- [ ] Capacity/error monitoring aktif.
- [ ] Storage backup/snapshot aktif.
- [ ] Malware-scanning gap dicatat dan accepted untuk controlled staging.

### Health dan Smoke Test

- [ ] `GET /up` lulus.
- [ ] `GET /api/health` lulus.
- [ ] `GET /api/v1/health` lulus.
- [ ] Database failure menghasilkan readiness 503 pada controlled test bila memungkinkan.
- [ ] `X-Request-ID` tersedia dan dapat dicari pada logs.
- [ ] Login, session restore, logout, dan CSRF lulus.
- [ ] Role authorization lulus.
- [ ] Ticket list/detail lulus.
- [ ] Attachment upload/download lulus.
- [ ] Notification ownership lulus.
- [ ] Knowledge Base scope lulus.
- [ ] Report scope lulus.
- [ ] Public OTP menggunakan SMTP sandbox lulus jika masuk scope.

### Fonnte Preparation

- [ ] Queue worker dan monitoring aktif sebelum WhatsApp enablement.
- [ ] Fonnte token/recipient/webhook secret berasal dari secret manager.
- [ ] Device profile berhasil diuji tanpa menampilkan secret.
- [ ] Canonical webhook endpoint ditentukan.
- [ ] Custom header capability atau gateway design disetujui.
- [ ] Direct backend webhook bypass ditutup.
- [ ] Webhook logs tidak merekam secret/body sensitif.
- [ ] Pending/unknown reconciliation procedure disetujui.
- [ ] Approved staging recipient ditentukan.
- [ ] Live send hanya dilakukan pada tahap terpisah dengan approval eksplisit.

### Monitoring dan Backup

- [ ] Central logs menerima Laravel/edge logs.
- [ ] HTTP 5xx dan latency alert aktif.
- [ ] Database monitor aktif.
- [ ] Queue worker/backlog/failed job alerts aktif.
- [ ] Scheduler heartbeat alert aktif.
- [ ] Storage capacity/error alert aktif.
- [ ] Fonnte failure/quota/device/webhook alerts aktif.
- [ ] Backup age alert aktif.
- [ ] Isolated database restore drill lulus.
- [ ] Isolated attachment restore drill lulus.
- [ ] RPO/RTO dan checksum evidence dicatat.

### Rollback Readiness

- [ ] Previous immutable artifact tersedia.
- [ ] Rollback owner dan trigger disetujui.
- [ ] Queue/scheduler pause procedure tersedia.
- [ ] Application rollback smoke test didefinisikan.
- [ ] Database rollback versus forward-fix decision point disetujui.
- [ ] Restore procedure dan data-loss window dikomunikasikan.

## 13. Status Akhir

```text
NOT READY FOR STAGING DEPLOYMENT
```

Alasan:

- RC1 code baseline valid dan telah lulus CI lengkap, termasuk MySQL 8.4.
- Runtime requirements dapat diidentifikasi dengan jelas dari source code.
- Recommended staging topology dan environment checklist telah tersedia.
- Namun infrastructure target, HTTPS/domain, reverse proxy, secret delivery, MySQL staging, durable storage, process supervision, monitoring, backup/restore, webhook gateway, dan operational owners belum tersedia atau belum disetujui.

Status dapat berubah menjadi `READY FOR STAGING DEPLOYMENT` setelah seluruh BLOCKER pada Bagian 11 memiliki konfigurasi konkret, owner, dan evidence preflight. Tahap 3A tidak melakukan deployment.

## 14. Source of Truth

Source utama yang direview:

- `backend/.env.example`
- `frontend/.env.example`
- `.github/workflows/ci.yml`
- `backend/routes/console.php`
- `backend/bootstrap/app.php`
- `backend/config/queue.php`
- `backend/config/filesystems.php`
- `backend/config/tickets.php`
- `backend/config/logging.php`
- `backend/config/session.php`
- `backend/config/cors.php`
- `backend/config/sanctum.php`
- `backend/config/whatsapp.php`
- `backend/app/Http/Controllers/Api/V1/HealthController.php`
- `backend/app/Jobs/SendWhatsAppNotificationJob.php`
- `backend/app/Services/WhatsAppNotificationService.php`
- `backend/app/Http/Controllers/Api/WhatsAppWebhookController.php`
- `frontend/src/config/env.ts`
- `frontend/src/api/client.ts`
- `frontend/vite.config.ts`
- `docs/deployment-architecture.md`
- `docs/deployment-runbook.md`
- `docs/rollback-runbook.md`
- `docs/scheduler-queue-runbook.md`
- `docs/backup-restore-runbook.md`
- `docs/logging-monitoring-runbook.md`
- `docs/crm-whatsapp-notification-setup.md`
- `docs/known-issues-and-risks.md`
- `docs/release-readiness-checklist.md`

## 15. Status File Laporan

Laporan Tahap 3A ini dibuat pada feature working tree setelah RC1 dibekukan, lalu disertakan dalam commit dokumentasi terpisah pada feature branch. File laporan tidak termasuk dalam baseline RC1 dan tidak mengubah:

```text
release/v1.0.0-rc1
v1.0.0-rc.1
6a04506a9b35656356aa4bafb194ac12219c65fa
```

Tidak dilakukan deployment, merge PR #2, perubahan production, live Fonnte send, atau perubahan baseline RC1.
