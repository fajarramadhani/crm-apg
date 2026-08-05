# HASIL UAT TAHAP 7 - CRM PUBLIK

## Status

**Belum siap deployment.** Automated SQLite regression is green, but production-like MySQL, SMTP, edge-log, and browser UAT evidence is unavailable in this environment.

## Automated Evidence

Executed on 4 August 2026:

```text
Backend full suite: 456 passed, 3,064 assertions
Attachment/public targeted suite: 51 passed, 389 assertions
Laravel Pint: passed
Composer validate --strict: passed
Composer audit --locked: no advisories
Frontend TypeScript: passed
Frontend Vite production build: passed
Frontend Prettier check: passed
SQLite migrate:fresh: passed
SQLite final migration rollback and reapply: passed
git diff --check: passed
```

Automated attachment scenarios include:

- Public-disk configuration rejection.
- Private PIC upload without path disclosure.
- Extensionless UUID storage names and requester visibility normalization.
- Dangerous executable/active-content double-extension rejection before storage.
- Authorized private download and cross-ticket/requester denial.
- Metadata failure restoring a quarantined file.
- Public attachment migration dry-run, checksum copy, resumable rerun, private backup, and rollback.
- Fixture environment/database guards and repeatable fixture creation.
- Public UAT evidence validation, cleanup compensation, and idempotent replay.

## Manual UAT Fixture

The command below now creates one repeatable synthetic public ticket in `ready_for_uat` and returns its synthetic tracking URL:

```text
php artisan stage7:seed-public-uat --confirm=SEED-STAGE7-PUBLIC-UAT
```

Safety controls:

- Allowed only in `local` or `testing`.
- `:memory:` or an explicitly disposable/test database name is required.
- No production recipient or customer data is created.
- Existing fixture and active tracking access are reused on rerun.

The fixture was validated by automated SQLite tests. It was not used for a browser/SMTP UAT run because a production-like backend, SMTP sandbox, and approved disposable browser database were not available.

## Not Executed

- MySQL 8/InnoDB migration and foreign-key compatibility.
- Two-process row-lock, deadlock, rotate/revoke, OTP verify, and accepted/rejected concurrency.
- SMTP sandbox delivery, delay, bounce, and OTP rendering.
- Chrome/Edge end-to-end UAT at `375x812`, `768x1024`, `1366x768`, and 200% zoom.
- Reverse proxy, CDN/WAF, APM, analytics, and exception-log token redaction samples.
- Real legacy attachment inventory and migration. The command was tested only with isolated fake storage.
- Malware/antivirus scanning.

## Required Sign-Off

Deployment remains blocked until all not-executed items have dated evidence, the migration reports zero public attachment rows, the manual checklist is signed, and backup/rollback restoration has been tested in the target environment.
