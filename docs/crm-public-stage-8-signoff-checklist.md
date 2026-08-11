# CHECKLIST SIGN-OFF TAHAP 8 - CRM PUBLIK

Tidak boleh memasukkan raw token, OTP, password, credential database, secret, atau email non-synthetic pada kolom bukti/catatan.

| Area | Skenario | Hasil | Tanggal | Bukti | PIC penguji | Catatan | Persetujuan |
|---|---|---|---|---|---|---|---|
| Repository | Branch, status, divergence, HEAD | Lulus | 2026-08-04 | Report §1 | OpenCode | Staged 0; no commit/push | Pending manusia |
| Baseline | Targeted Tahap 1-7 | Lulus | 2026-08-04 | 82 tests / 883 assertions | OpenCode | Synthetic test data | Pending QA |
| MySQL | Version, InnoDB, strict mode | Lulus | 2026-08-04 | Report §3 | OpenCode | Disposable loopback | Pending DBA |
| Migration | Fresh migration | Lulus setelah fix | 2026-08-04 | Report §5 | OpenCode | FK identifier fixed | Pending DBA |
| Migration | Rollback/reapply empat migration | Lulus | 2026-08-04 | Report §5 | OpenCode | Constraints retained | Pending DBA |
| Backend | Full suite SQLite | Lulus | 2026-08-04 | 456 / 3,064 | OpenCode | Final run | Pending QA |
| Backend | Full suite MySQL | Lulus | 2026-08-04 | 456 / 3,064 | OpenCode | Final run | Pending QA |
| Concurrency | Existing two-process harness | Lulus | 2026-08-04 | 5/5 scenarios | OpenCode | MySQL separate processes | Pending QA |
| Concurrency | Public tracking race matrix | Tidak dapat diuji lengkap | 2026-08-04 | Report §7 | Belum ditunjuk | Harness belum tersedia | Blocker |
| Concurrency | OTP/UAT/confirmation race matrix | Tidak dapat diuji lengkap | 2026-08-04 | Report §7 | Belum ditunjuk | Sequential coverage only | Blocker |
| Attachment | Dry-run inventory | Lulus synthetic | 2026-08-04 | Aggregate inventory | OpenCode | No path logged | Pending Storage Owner |
| Attachment | Backup sebelum canary | Lulus synthetic | 2026-08-04 | DB/file backup + checksum | OpenCode | Temporary backup | Pending DBA |
| Attachment | Canary forward | Lulus synthetic | 2026-08-04 | 1 processed, 0 failed | OpenCode | Isolated roots | Pending QA |
| Attachment | Rollback drill | Lulus synthetic | 2026-08-04 | 1 processed, 0 failed | OpenCode | Source restored | Pending QA |
| Attachment | Re-forward dan public=0 | Lulus synthetic | 2026-08-04 | Public 0, orphan 0, missing 0 | OpenCode | Disposable dataset only | Pending QA |
| Attachment | Retained staging full migration | Tidak diuji | 2026-08-04 | Belum ada | Belum ditunjuk | Backup wajib | Blocker |
| SMTP | SMTP transport ke Mailpit | Lulus | 2026-08-04 | Sanitized boolean checks | OpenCode | Synthetic recipient | Pending QA |
| SMTP | Full OTP workflow, retry, failure | Tidak diuji lengkap | 2026-08-04 | Functional tests only | Belum ditunjuk | Queue/bounce pending | Blocker |
| Queue | Worker start dan graceful empty exit | Lulus | 2026-08-04 | Worker command exit 0 | OpenCode | No queued jobs exist | Pending DevOps |
| Queue | Retry, timeout, failed job, reconnect | Tidak dapat diuji | 2026-08-04 | No ShouldQueue job | Belum ditunjuk | Operational gap | Blocker |
| Scheduler | Schedule list/timezone/locks | Lulus | 2026-08-04 | Report §13 | OpenCode | Asia/Jakarta | Pending DevOps |
| Scheduler | Cleanup dua kali | Lulus | 2026-08-04 | Active tracking preserved | OpenCode | Business history preserved | Pending QA |
| Browser | Chrome automated suite | Lulus | 2026-08-04 | Sanitized summary/screenshots | OpenCode | Synthetic fixtures | Pending QA |
| Browser | Edge automated suite | Lulus | 2026-08-04 | Sanitized summary/screenshots | OpenCode | Synthetic fixtures | Pending QA |
| Browser | Public request/history/UAT/confirmation | Tidak diuji lengkap | 2026-08-04 | Belum ada | Belum ditunjuk | Manual E2E required | Blocker |
| Accessibility | Automated labels/focus/overflow | Lulus | 2026-08-04 | Browser checks | OpenCode | Five viewports | Pending Accessibility QA |
| Accessibility | 200% zoom dan screen reader | Tidak diuji | 2026-08-04 | Belum ada | Belum ditunjuk | Manual test required | Blocker |
| Proxy | Nginx config syntax | Lulus | 2026-08-04 | `nginx -t` | OpenCode | Loopback validation | Pending DevOps |
| Proxy | Tracking access-log redaction | Lulus | 2026-08-04 | Five sanitized lines | OpenCode | Raw token absent | Pending Security |
| Observability | CDN/WAF/APM/analytics redaction | Tidak dapat diuji | 2026-08-04 | Belum ada | Belum ditunjuk | External systems unavailable | Blocker |
| Errors | Generic API error contracts | Lulus automated | 2026-08-04 | Full suites | OpenCode | Debug false browser run | Pending Security |
| Errors | Live 401-500 proxy matrix | Perlu retest | 2026-08-04 | Partial only | Belum ditunjuk | Injected 500 pending | Blocker |
| Dependency | Composer audit | Lulus | 2026-08-04 | 0 advisory | OpenCode | Locked dependencies | Pending Security |
| Dependency | Frontend critical audit | Lulus | 2026-08-04 | 0 critical | OpenCode | One high non-RSC | Pending Security |
| Final | Format, typecheck, build, Pint, diff | Lulus | 2026-08-04 | Report §21 | OpenCode | No commit/push | Pending QA |
| Release | Deployment production | Tidak dilakukan | 2026-08-04 | Larangan Tahap 8 | N/A | Correct behavior | N/A |
| Final status | Siap UAT lanjutan | Bersyarat | 2026-08-04 | Report §25 | Belum ditunjuk | Bukan deployment approval | Pending seluruh approver |
