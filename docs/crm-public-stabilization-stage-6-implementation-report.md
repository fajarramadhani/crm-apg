# LAPORAN IMPLEMENTASI TAHAP 6 - STABILISASI DAN KESIAPAN UAT CRM PUBLIK

## 1. Kondisi Awal Repository

- Branch aktif: `feature/crm-simplified-dynamic-workflow`.
- Branch tidak ahead atau behind dari `origin/feature/crm-simplified-dynamic-workflow`.
- Commit terakhir tetap `a82892f`.
- Tidak ada staged changes.
- Pemeriksaan awal melaporkan 64 tracked file modified dan 46 file untracked, mencakup perubahan lokal Tahap 3, 4, 5, laporan, serta pekerjaan existing lain.
- Seluruh perubahan existing dipertahankan. Tidak dilakukan checkout, stash, reset, revert, atau penghapusan file.
- Tidak dilakukan commit atau push pada Tahap 6.

## 2. Baseline Regression Test

Targeted Tahap 1-5 sebelum perubahan:

```text
42 passed, 668 assertions
```

Coverage mencakup form publik, tracking, history OTP, panel access management, rotate/revoke, public UAT/confirmation, attachment, credential scoping, idempotency, authorization, dan timeline publik.

## 3. Hasil Validasi MySQL

**Tidak dijalankan.**

- Docker tidak tersedia.
- MySQL client/server lokal tidak tersedia.
- Tidak digunakan database development atau production sebagai pengganti.

Akibatnya, hal berikut belum dapat diklaim:

- Compatibility `nullable()->change()` dengan foreign key InnoDB.
- Lock serialization `lockForUpdate()`.
- Deadlock dan retry behavior.
- Concurrent issue/rotate/revoke/UAT/confirmation.
- JSON column, index plan, strict timestamp, dan cascade behavior di MySQL.

MySQL migration dan concurrency test menjadi prasyarat wajib sebelum deployment.

## 4. Hasil Pengujian Concurrency

SQLite tidak memberikan row-lock behavior yang setara dengan InnoDB, sehingga test paralel realistis tidak dijalankan dan tidak diklaim lulus.

Invariant yang telah diverifikasi secara deterministic/sequential:

- Satu tracking token aktif setelah rotate.
- Retry rotate dengan key sama mengembalikan receipt sama.
- Revoke replay tidak membuat audit duplikat.
- OTP challenge cooldown tidak mengirim OTP kedua.
- OTP consumed tidak dapat diverifikasi ulang.
- Action credential consumed tidak menjalankan mutation kedua.
- Idempotency payload conflict menghasilkan `409`.
- Attachment dan event tidak duplikat pada replay.
- Accepted confirmation tidak menawarkan action kedua.
- Credential lama invalid setelah status atau tracking record berubah.

Test InnoDB dua-process tetap diperlukan untuk race accepted/rejected, dua verify, dua rotate dengan key berbeda, cleanup race, dan deadlock behavior.

## 5. Audit Dependency Backend

Temuan awal:

- `guzzlehttp/guzzle` versi `7.15.1`.
- Dibawa oleh `laravel/framework` dengan constraint `^7.8.2`.
- Advisory:
  - High `CVE-2026-69246` / `GHSA-v5mv-p594-2x33`.
  - Medium `CVE-2026-69245` / `GHSA-f7vp-7xgx-4w4r`.
- Patch aman tersedia pada major yang sama: `7.15.2`.

Perbaikan:

```text
composer update guzzlehttp/guzzle --with-dependencies --no-interaction
```

Hanya Guzzle berubah dari `7.15.1` ke `7.15.2`. Laravel dan dependency utama lain tidak dinaikkan.

Hasil akhir:

- `composer audit --locked --no-interaction`: tidak menemukan advisory.
- Full regression lulus setelah update.

## 6. Audit Dependency Frontend

Temuan:

- `react-router-dom 7.18.1` membawa `react-router 7.18.1`.
- Advisory high `GHSA-qwww-vcr4-c8h2` memengaruhi `react-router >=7.12.0 <8.3.0`.
- Patch tersedia pada React Router `8.3.0`, yaitu major upgrade.

Tidak dilakukan upgrade karena:

- Memerlukan audit/migrasi API major.
- Advisory berkaitan dengan RSC Mode, sedangkan aplikasi ini menggunakan React SPA + Vite dan tidak menggunakan React Router RSC action execution.
- Major upgrade di luar scope stabilisasi terarah.

Mitigasi sementara:

- Tidak mengaktifkan RSC mode.
- Backend tetap menjadi boundary authentication/authorization.
- Tidak mengekspos server action React Router.
- Upgrade major harus diproses sebagai pekerjaan dependency terpisah dengan regression penuh.

Tidak ada vulnerability critical frontend.

## 7. Audit Key Rotation

Masalah existing:

- Record tracking menyimpan `key_version`, tetapi service selalu memakai satu key aktif.
- Lookup raw token tetap bekerja setelah key berubah karena menggunakan SHA-256 hash.
- Receipt reconstruction panel, history, submission replay, dan management replay gagal bila key lama tidak tersedia.

Perbaikan:

- Menambahkan `PublicTicketTrackingKeyRing` tanpa migration schema.
- Formula derivasi token dipertahankan persis.
- Legacy single-key mode tetap kompatibel.
- Ring mode memakai JSON version-to-key map.
- Token baru memakai current key version.
- Token lama direkonstruksi menggunakan stored `key_version`.
- Production boot, termasuk console/scheduler/queue, gagal bila konfigurasi key tidak aman.

Konfigurasi:

```env
PUBLIC_TICKET_TRACKING_KEY=
PUBLIC_TICKET_TRACKING_KEYS='{"1":"base64:<old>","2":"base64:<new>"}'
PUBLIC_TICKET_TRACKING_KEY_VERSION=2
```

Test mencakup deterministic formula, old-key reconstruction setelah active version berubah, hash lookup tanpa old key, active version missing, dan ambiguous dual configuration.

## 8. Audit Log Redaction

Kontrol aplikasi:

- `Referrer-Policy: no-referrer` dan `Cache-Control: no-store, private` pada response publik.
- Static `<meta name="referrer" content="no-referrer">` ditambahkan ke HTML sebelum React mount.
- Audit reason meredaksi URL, token Base64URL, dan hex hash.
- Notification tidak menyimpan tracking URL/token.
- Exception handler tracking route hanya mencatat request ID dan exception class, bukan exception object yang dapat membawa route parameter.
- Tidak ditemukan analytics/session replay SDK pada frontend.

Batasan:

- Aplikasi tidak dapat mengontrol access log reverse proxy, load balancer, WAF, CDN, APM, atau browser history.
- Dokumentasi redaction Nginx/APM ditambahkan di `docs/crm-public-production-operations.md`.

Reverse proxy redaction wajib diverifikasi dari sample log sebelum public traffic.

## 9. Audit Credential

Credential yang diaudit:

- Tracking token.
- History challenge/access token dan OTP.
- Action challenge/access token dan OTP.
- Idempotency key.

Hasil:

- Raw credential tidak disimpan di database.
- OTP memiliki expiry, cooldown, attempts, dan one-time consumption.
- Access token memiliki absolute expiry dan revoke.
- Action credential terikat ticket, branch, identity, action, status hash, tracking record, dan latest generation.
- History token tidak dapat menjadi action token.
- Action rate limiter kini memiliki bucket IP, tracking hash, dan action-access hash.
- Malformed challenge diproses melalui generic verification failure service, bukan fingerprint validation berbeda.
- Public `429` memakai pesan generik.
- Action idempotency expiry diselaraskan dengan action access expiry dan expired row diperiksa sebelum replay.

## 10. Audit Attachment

Public UAT evidence:

- Maksimal 10 file, 10 MB/file.
- Extension dan MIME allowlist.
- Executable ditolak sebelum mutation/storage.
- UUID random storage name.
- Private ticket storage abstraction.
- `uploaded_by = null`, visibility requester.
- Cleanup file pada transaction/insert failure.
- Replay tidak menggandakan attachment.

Test tambahan memverifikasi executable rejection tanpa row/file dan cleanup credential active/expired.

Risiko legacy terpisah:

- Audit menemukan PIC attachment flow lama menyimpan file pada `public` disk, termasuk visibility internal, dan dapat mengembalikan storage path.
- Attachment controller lama belum menggunakan compensated transaction/storage service secara konsisten.

Masalah ini tidak diubah di Tahap 6 karena membutuhkan centralization attachment lintas flow dan regression luas. Ini merupakan blocker deployment yang harus ditangani sebelum sistem dinyatakan production-ready.

Antivirus/content scanning belum tersedia dan direkomendasikan untuk production.

## 11. UAT Visual dan Checklist Manual

Chrome dan Edge ditemukan pada lokasi standar. Vite server awalnya tidak berjalan; instance sementara dijalankan untuk smoke.

Hasil smoke:

- `/request` memuat frontend shell pada viewport `375x812` tanpa overflow yang terlihat.
- Backend API tidak berjalan, sehingga halaman berhenti pada loading shell.
- Command screenshot tidak menghasilkan validasi state bisnis pada seluruh viewport.

Karena fixture public tracking/OTP/UAT/Supervisor tidak tersedia, visual test lengkap **tidak diklaim berhasil**.

Checklist manual rinci ditambahkan:

```text
docs/crm-public-uat-manual-checklist.md
```

Checklist mencakup `375x812`, `768x1024`, `1366x768`, zoom, keyboard, screen reader, error/loading, OTP, accepted/rejected, attachment, panel Supervisor, dan log inspection.

## 12. Perbaikan Accessibility

- Shared modal kini memiliki initial focus, Tab/Shift+Tab trap, Escape, body scroll lock, dan focus restoration.
- Backdrop tidak lagi menjadi focusable button.
- Public UAT action dialog memiliki focus trap, Escape revoke/close, focus restoration, dan scroll lock.
- Radio cards dan file uploader memiliki visible keyboard focus.
- Icon close/remove targets dinaikkan mendekati minimum 44px.
- Success/expiry state memiliki live status/alert semantics.
- Shared Toast memiliki `role`, `aria-live`, dan labeled close control.
- Backend outcome-specific notes requirement tidak lagi dibuang oleh frontend adapter.

Perbaikan field-level error association pada seluruh form publik masih dapat ditingkatkan pada tahap aksesibilitas khusus.

## 13. Cleanup dan Scheduler

Command terdaftar:

```text
public-history:cleanup   hourly
public-actions:cleanup   hourly
idempotency:cleanup      daily
```

Perbaikan:

- History/action cleanup memakai bounded batch 500 row.
- Command aman dijalankan berulang.
- Active credential dipertahankan.
- Expired credential dihapus.
- Audit bisnis tidak dihapus.
- Expiry indexes tersedia pada tabel credential/idempotency.

Test memastikan double-run cleanup tidak menghapus active action credentials.

## 14. Dokumentasi Environment

`.env.example` mencakup:

```env
FRONTEND_URL=
PUBLIC_TICKET_TRACKING_EXPIRY_DAYS=
PUBLIC_TICKET_TRACKING_KEY=
PUBLIC_TICKET_TRACKING_KEYS=
PUBLIC_TICKET_TRACKING_KEY_VERSION=1
PUBLIC_HISTORY_DRIVER=mail
PUBLIC_HISTORY_OTP_EXPIRY_MINUTES=10
PUBLIC_HISTORY_MAX_ATTEMPTS=5
PUBLIC_HISTORY_RESEND_COOLDOWN_SECONDS=60
PUBLIC_HISTORY_ACCESS_TTL_MINUTES=15
PUBLIC_HISTORY_IDENTITY_KEY=
PUBLIC_HISTORY_OTP_PEPPER=
PUBLIC_ACTION_ACCESS_TTL_MINUTES=10
```

Dokumentasi production operations mencakup generation secret, SMTP, scheduler, queue, private storage, key rotation, cleanup, reverse-proxy redaction, dan readiness checklist.

## 15. Daftar File Tahap 6

File utama yang ditambah/diubah khusus stabilisasi:

- `backend/composer.lock`
- `backend/.env.example`
- `backend/config/public_tracking.php`
- `backend/app/Services/PublicTicketTrackingKeyRing.php`
- `backend/app/Services/PublicTicketTrackingService.php`
- `backend/app/Services/PublicTicketActionService.php`
- `backend/app/Providers/AppServiceProvider.php`
- `backend/bootstrap/app.php`
- `backend/app/Console/Commands/CleanupPublicRequestHistoryCommand.php`
- `backend/app/Console/Commands/CleanupPublicTicketActionsCommand.php`
- `backend/app/Http/Requests/Api/V1/VerifyPublicHistoryChallengeRequest.php`
- `backend/app/Http/Requests/Api/V1/VerifyPublicTicketActionChallengeRequest.php`
- `backend/tests/Feature/PublicTicketTrackingKeyRingTest.php`
- `backend/tests/Feature/PublicTicketTrackingTest.php`
- `backend/tests/Feature/PublicTicketActionTest.php`
- `frontend/index.html`
- `frontend/src/components/ui.tsx`
- `frontend/src/pages/public/PublicTicketTracking.tsx`
- `frontend/src/services/publicTicketService.ts`
- `docs/crm-public-production-operations.md`
- `docs/crm-public-uat-manual-checklist.md`
- `docs/crm-public-stabilization-stage-6-implementation-report.md`

Semua perubahan Tahap 3-5 tetap dipertahankan.

## 16. Hasil Seluruh Pengujian

Targeted final Tahap 1-6:

```text
49 passed, 700 assertions
```

Full backend:

```text
451 passed, 3.027 assertions
```

Validasi lain:

- `vendor/bin/pint --test`: lulus.
- `composer validate --no-interaction`: lulus.
- `composer audit --locked --no-interaction`: lulus, tidak ada advisory.
- `corepack pnpm format:check`: lulus.
- `corepack pnpm typecheck`: lulus.
- `corepack pnpm build`: lulus.
- `corepack pnpm audit --prod --audit-level critical`: tidak ada critical; satu high React Router tetap ada.
- `git diff --check`: tidak menemukan whitespace error; hanya warning LF/CRLF.
- Public route inspection: 14 route terdaftar.
- Scheduler inspection: cleanup history/action/idempotency terdaftar.
- SQLite `migrate:fresh`: lulus.
- Rollback Tahap 5: lulus.
- Rollback dua migration Tahap 4: lulus.
- Migrasi ulang Tahap 4-5: lulus.

## 17. Risiko Tersisa

- MySQL/InnoDB belum diuji.
- Real two-process concurrency belum diuji.
- Public visual/OTP fixture belum tersedia.
- React Router high advisory belum dipatch karena memerlukan major upgrade; mitigasi RSC didokumentasikan.
- Legacy PIC attachment public-disk dan non-centralized cleanup belum diperbaiki.
- Reverse proxy/APM redaction belum dapat diverifikasi dari repository.
- Production SMTP/mail delivery belum diuji.
- Antivirus/content scanning belum tersedia.
- Field-level accessibility error associations masih perlu audit lanjutan.

## 18. Rekomendasi Sebelum Deployment

1. Jalankan full migration, full suite, dan concurrency suite pada MySQL 8/InnoDB dedicated testing database.
2. Centralize seluruh attachment persistence dan pindahkan PIC/internal attachment dari public disk.
3. Jalankan manual UAT checklist dengan seeded public ticket/action fixtures dan production-like SMTP sandbox.
4. Terapkan serta verifikasi reverse-proxy/APM tracking-token redaction dari sample logs.
5. Audit dan rencanakan React Router 8 migration atau vendor mitigation resmi.
6. Verifikasi production config fail-fast, scheduler, queue worker, private storage, dan key-ring rollout.

## 19. Status Menuju Tahap 7

**Siap untuk UAT internal dengan prasyarat. Belum siap deployment.**

Regression, SQLite migration, backend dependency patch, credential hardening, key rotation, cleanup, dan build telah lulus. Deployment tetap diblokir sampai MySQL/concurrency validation, legacy attachment remediation, visual UAT, dan infrastructure log redaction selesai.

Tidak dilakukan commit, push, merge, rebase, reset, force-push, atau deployment.
