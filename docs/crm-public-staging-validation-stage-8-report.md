# LAPORAN VALIDASI STAGING TAHAP 8 - CRM PUBLIK

## 1. Kondisi Awal Repository

- Tanggal validasi: 4 Agustus 2026.
- Branch: `feature/crm-simplified-dynamic-workflow`.
- HEAD: `a82892f feat(crm): add secure public request history backend`.
- Divergence terhadap upstream: `0 ahead`, `0 behind`.
- Kondisi awal: staged `0`, modified `46`, untracked `34`, total `80` entry.
- Baseline Tahap 1-7: `82 passed`, `883 assertions`.
- Perubahan Tahap 3-7 tetap tersedia. Tidak dilakukan reset, stash, checkout, revert, clean, commit, push, merge, atau deployment.

## 2. Environment Staging

Validasi memakai environment testing disposable lokal, bukan database development atau production:

- Database: `crm_stage8_testing`, bind loopback pada port non-default, dibuat ulang dari kosong.
- Data: synthetic `@stage7.invalid` dan `@stage9.invalid`.
- Debug: `false`.
- Storage public/private: root temporary terpisah dari storage workspace.
- Secret/password/OTP/tracking token dibuat sementara, tidak dicetak, tidak ditulis ke repository, dan dihapus dari process environment.
- MySQL, backend, frontend, Mailpit, dan Nginx dihentikan setelah masing-masing validation run.

## 3. Versi Software

| Komponen | Versi |
|---|---|
| OS | Windows 11 Pro, NT 10.0.26200.0 |
| PHP | 8.5.0 |
| Composer | 2.8.12 |
| Node.js | 24.18.0 |
| pnpm | 11.13.0 |
| MySQL | 8.4.9 Community Server |
| Engine | InnoDB |
| Nginx | 1.31.3 |
| Mailpit | 1.29.5 |
| Chrome | 150.0.7871.187 |
| Edge | 151.0.4129.59 |

MySQL memakai strict mode dengan `STRICT_TRANS_TABLES`, `ONLY_FULL_GROUP_BY`, `NO_ZERO_DATE`, dan `NO_ENGINE_SUBSTITUTION`.

## 4. Baseline Regression

Targeted public submission, tracking, history OTP, public action, key ring, attachment, authorization, dan PIC workspace:

```text
82 passed, 883 assertions
```

## 5. Hasil Migration MySQL

Run pertama menemukan blocker nyata:

```text
SQLSTATE[42000] 1059: foreign-key identifier is longer than 64 characters
```

Akar masalah adalah nama foreign key otomatis pada tabel public action. Migration belum committed/shipped, sehingga relasi tetap dipertahankan dan hanya diberi nama constraint eksplisit yang pendek.

Retest MySQL 8.4.9:

- `migrate:fresh`: lulus.
- `migrate:status`: seluruh migration `Ran`.
- Rollback empat migration terakhir: lulus.
- Reapply empat migration: lulus.
- Nullable foreign key, `change()`, JSON, unique/composite index, Sanctum, public tracking/history/action credential, confirmation nullable, dan attachment schema: lulus.

## 6. Hasil Full Suite MySQL

Run pertama setelah migration fix menemukan satu kegagalan:

- MySQL JSON mengembalikan key idempotency result dalam urutan berbeda.
- Response replay semantik benar, tetapi strict array comparison gagal.
- Replay dinormalisasi kembali ke kontrak `action`, `outcome`, `status`.

Hasil final:

```text
456 passed, 3,064 assertions
```

## 7. Hasil Concurrency

Harness dua proses dan koneksi MySQL terpisah lulus untuk lima skenario:

- Competing primary assignment.
- Duplicate submit for approval.
- Duplicate approval.
- Competing workflow activation.
- Competing reassignment.

Invariant yang terbukti: satu primary aktif, satu transition final, history/notification count sesuai, workflow aktif tunggal, dan ticket-assignment konsisten. Conflict kedua berhenti terbatas dan tidak retry tanpa batas.

Belum terbukti dengan true two-process harness:

- Seluruh 18 race public tracking/OTP/UAT/confirmation yang diminta.
- Cleanup berbarengan verify.
- Rotate/revoke berbarengan.
- Accepted/rejected public action berbarengan.
- Upload evidence public berbarengan.

Sequential MySQL/SQLite tests memverifikasi consumption, idempotency, duplicate attachment/comment/history prevention, dan stale credential rejection, tetapi bukan pengganti race proof. Ini blocker deployment.

## 8. Inventaris Attachment

Dry-run synthetic isolated:

```json
{"public_records_total":1,"selected_records":1,"available_files":1,"missing_files":0,"inconsistent_metadata":0,"available_bytes":36,"impacted_tickets":1,"suspicious_names":0}
```

Dry-run tidak lagi mencetak storage path. Root storage default workspace ditemukan memiliki file existing yang bukan milik database disposable; file tersebut tidak dihapus. Validation kemudian diulang pada public/private roots temporary yang benar-benar terisolasi.

## 9. Hasil Canary dan Rollback

- Backup database temporary dibuat dan diberi SHA-256 tanpa mencatat isi atau credential.
- Backup private source dibuat sebelum canary.
- Forward canary: `1 processed, 0 failed`.
- Private metadata dan checksum backup/target diverifikasi.
- Public source dihapus setelah metadata commit.
- Rollback: `1 processed, 0 failed`.
- Public source dan metadata berhasil dipulihkan.
- Re-forward: `1 processed, 0 failed`.

## 10. Hasil Migrasi Attachment Staging

Pada dataset synthetic isolated:

```json
{"public_attachment_records":0,"private_attachment_records":1,"orphan_private_ticket_files":0,"missing_private_ticket_files":0}
```

Gate `disk=public = 0` lulus hanya untuk database disposable ini. Migrasi retained staging data nyata, batch progress, dan sample checksum lintas dataset belum dijalankan. Backup migration tidak dihapus.

## 11. Hasil SMTP Sandbox

Mailpit menangkap satu email OTP synthetic melalui transport SMTP nyata:

- Captured count: `1`.
- Subject sesuai.
- OTP ada pada body, tetapi nilainya tidak dicetak.
- Masa berlaku tercantum.
- Tracking token, hash, internal ID, dan data PIC tidak ada.

Functional tests meliputi wrong/expired OTP, cooldown, attempts, duplicate challenge, dan generic not-found behavior memakai test delivery. SMTP queue delay, retry, bounce, failed job, dan full browser OTP workflow belum diuji.

## 12. Hasil Queue

- Database queue schema tersedia.
- `queue:work database --stop-when-empty --tries=3 --timeout=30` start dan graceful exit lulus.
- Aplikasi saat ini tidak memiliki job `ShouldQueue`, sehingga retry, timeout, failed job, connection recovery, dan duplicate delivery belum dapat dibuktikan secara nyata.
- Command operasional tetap: `php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-time=3600`.

## 13. Hasil Scheduler

- Timezone: `Asia/Jakarta`.
- `schedule:list` memuat SLA/inactivity setiap 15 menit, history/action cleanup hourly, dan idempotency cleanup daily.
- Seluruh schedule memakai `withoutOverlapping(20)` dan `onOneServer()`.
- Tiga cleanup dijalankan dua kali; active tracking dan business history tetap ada.
- `schedule:run` lulus dan melaporkan tidak ada task yang due pada waktu run.
- Heartbeat/supervisor scheduler dan forced failure logging belum diuji.

## 14. Hasil UAT Browser

Harness Chrome DevTools Protocol awalnya gagal karena class `Cdp` dipakai sebelum initialization, stale role session, loading race, dan expected copy lama. Harness diperbaiki dan diulang.

Hasil final:

```text
706 checks, 706 passed, 0 failed
```

Coverage:

- Chrome dan Edge.
- `1440x900`, `1366x768`, `768x1024`, `390x844`, `360x800`.
- Requester authenticated list/create/detail.
- Supervisor dashboard/list/detail/self-approval/legacy.
- PIC dashboard/list/detail.
- Workflow list/detail/editor.
- Route, content, overflow, labels, icon buttons, table headers, keyboard focus, console, network, dan screenshots synthetic.

Belum tercakup:

- Public request submit/receipt/copy link.
- Public history OTP end-to-end.
- Public UAT/confirmation end-to-end.
- Tracking rotate/revoke saat dialog aktif.
- Attachment upload melalui browser.
- Clipboard fallback.
- Exact `375x812` dan zoom 200%.

## 15. Hasil Accessibility

Perbaikan terarah:

- Label search/status requester.
- Association dan error description file uploader.
- Label filter Supervisor dan PIC.
- Label analysis, assignment, approval, dan pagination controls.
- Harness role reset dan keyboard focus stabilization.

Automated browser checks atas field label, icon button, table header, keyboard focus, overflow, dan modal visibility lulus pada seluruh viewport. Screen reader manual, Space/Escape lengkap, live-region announcement, dan zoom 200% tetap perlu UAT manusia.

## 16. Hasil Log Redaction

Nginx configuration test lulus. Lima synthetic request menghasilkan hanya:

```text
GET /track/[REDACTED] 204
GET /api/v1/public/tickets/track/[REDACTED] 204
POST /api/v1/public/tickets/track/[REDACTED]/actions/challenge 204
POST /api/v1/public/tickets/track/[REDACTED]/actions/verify 204
POST /api/v1/public/tickets/track/[REDACTED]/actions/submit 204
```

Raw token dan `X-Public-Action-Token` tidak ada pada access log. CDN/WAF/APM/analytics/exception monitoring tidak tersedia, sehingga bukti redaksi end-to-end infrastruktur tetap pending.

## 17. Hasil Error Response Inspection

Dengan `APP_DEBUG=false`, full feature suites memverifikasi generic public error, authorization, not-found, conflict, validation, dan rate-limit contract tanpa credential material. Live reverse-proxy samples untuk seluruh `401/403/404/409/422/429/500`, khususnya injected `500`, belum dikumpulkan. Tidak ada stack trace pada browser/network evidence.

## 18. Hasil Dependency Audit

- Composer: tidak ada advisory.
- Frontend production dependencies: `0 critical`.
- React Router `7.18.1`: satu advisory high `GHSA-qwww-vcr4-c8h2` untuk RSC mode.
- Aplikasi adalah Vite SPA non-RSC. Major upgrade `8.3.0` tidak dilakukan sesuai larangan.

## 19. Daftar Bug dan Perbaikan

1. MySQL foreign-key identifier terlalu panjang: nama constraint dipendekkan eksplisit.
2. MySQL JSON replay key order tidak deterministik: response replay dinormalisasi.
3. Storage canary awal tidak terisolasi: root public/private dibuat configurable dan canary diulang.
4. Attachment dry-run mengekspos path dan kurang inventory: path dihapus, aggregate inventory ditambahkan.
5. Browser harness initialization/session/loading/expected copy usang: diperbaiki dan retest lulus.
6. Field/filter/icon button tanpa accessible name: label/ARIA ditambahkan.

## 20. Daftar File yang Diubah pada Tahap 8

- `backend/.env.example`
- `backend/config/filesystems.php`
- `backend/database/migrations/2026_08_04_000001_create_public_ticket_action_credentials.php`
- `backend/app/Services/PublicTicketActionService.php`
- `backend/app/Console/Commands/MigratePublicTicketAttachmentsCommand.php`
- `frontend/scripts/stage9-browser-validation.mjs`
- `frontend/src/pages/user/TicketHistory.tsx`
- `frontend/src/pages/user/CreateTicket.tsx`
- `frontend/src/pages/pic/PicTicketList.tsx`
- `frontend/src/components/pic/PicTicketFilters.tsx`
- `frontend/src/components/supervisor/SupervisorTicketFilters.tsx`
- `frontend/src/components/supervisor/TicketAnalysisPanel.tsx`
- `frontend/src/components/supervisor/TicketAssignmentPanel.tsx`
- `frontend/src/components/supervisor/TicketApprovalPanel.tsx`
- `infrastructure/nginx/stage8-redaction-validation.conf`
- Dokumen laporan dan checklist Tahap 8.

## 21. Final Validation

| Gate | Hasil |
|---|---|
| SQLite full suite | 456 passed, 3,064 assertions |
| MySQL full suite | 456 passed, 3,064 assertions |
| MySQL fresh/rollback/reapply | Lulus |
| Existing two-process concurrency | 5/5 lulus |
| Browser automation | 706/706 lulus |
| Laravel Pint | Lulus |
| Composer validate/audit | Lulus, 0 advisory |
| Frontend format/typecheck/build | Lulus |
| Frontend critical audit | 0 critical, 1 high documented |
| `git diff --check` | Lulus |

## 22. Risiko Tersisa

- Public concurrency matrix belum true two-process lengkap.
- Public browser OTP/UAT/confirmation belum end-to-end.
- SMTP retry/failure/queue behavior belum nyata.
- Exact 200% zoom dan screen reader belum diuji.
- APM/CDN/WAF log redaction belum dibuktikan.
- Retained staging attachment dataset belum dimigrasikan.
- Malware scanning belum tersedia.
- React Router high advisory tetap dimitigasi melalui non-RSC.

## 23. Blocker Deployment

- Bukti 18 public concurrency races belum lengkap.
- Public browser UAT dan manual accessibility sign-off belum lengkap.
- Queue retry/failure dan full SMTP workflow belum lengkap.
- APM/edge ecosystem redaction belum lengkap.
- Migrasi attachment pada retained staging data belum dilakukan.
- Sign-off manusia belum tersedia.

## 24. Rekomendasi

1. Lanjutkan UAT publik pada environment retained staging dengan Mailpit dan data synthetic.
2. Tambahkan public concurrency workers untuk seluruh race matrix dan jalankan pada MySQL.
3. Jalankan 200% zoom, screen reader, clipboard, dan public action browser scenarios.
4. Verifikasi CDN/WAF/APM logs dengan traffic synthetic yang sama.
5. Backup retained staging, jalankan attachment inventory/canary/rollback/full batch, dan pertahankan backup.
6. Minta QA, DevOps, Security, dan product owner mengisi checklist sign-off.

## 25. Status Akhir

**Siap UAT lanjutan.** Bukan siap deployment atau deployment terbatas. Infrastruktur lokal disposable dan automated gates utama sudah memberi bukti kuat, tetapi blocker public concurrency, public E2E, queue/SMTP failure behavior, APM, retained-data migration, dan sign-off masih terbuka.
