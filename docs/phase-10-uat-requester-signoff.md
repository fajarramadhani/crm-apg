# Phase 10 — UAT Execution, Requester Revision, and Sign-off

## 1. Ruang Lingkup

Phase 10 mengimplementasikan Tic Hub untuk proses User Acceptance Testing (UAT):

`ready_for_uat → uat_assignment → uat_in_progress → uat_failed → development_in_progress → uat_retest → uat_in_progress → uat_approved`

Approval bisnis, deployment, monitoring, notifikasi, dan Knowledge Base tidak termasuk dalam phase ini.

## 2. Database & Model

Ditambahkan migration `2026_07_17_112329_create_uat_tables` dengan tabel:

- `ticket_uat_assignments`
- `ticket_uat_scenarios`
- `ticket_uat_runs`
- `ticket_uat_results`
- `ticket_uat_findings`
- `ticket_uat_finding_histories`
- `ticket_uat_finding_sequences`

Ditambahkan model UAT dan relasi pada `Ticket`, `User`, `TicketAttachment`, serta `TicketComment`.

## 3. Workflow & Authorization

- IT Lead dapat melihat queue assignment UAT.
- IT Lead hanya dapat menugaskan requester pemilik tiket sebagai tester.
- Requester hanya dapat menjalankan UAT pada tiket yang ditugaskan kepadanya.
- Semua transisi UAT dicatat dalam status history tiket.
- Finding UAT menggunakan lifecycle `open → in_progress → resolved → retest → verified` atau `reopened`.
- PIC dapat mengerjakan finding UAT dan mengajukan retest setelah memenuhi gate worklog, internal test, dan progress 100%.
- Attachment evidence UAT mengikuti authorization dan visibility yang sama dengan fase testing sebelumnya.

## 4. Backend API

Ditambahkan controller, request, resource, service, event, policy, permission, dan route untuk:

- UAT assignment queue dan assignment requester.
- Start UAT.
- Scenario UAT.
- UAT run dan result.
- UAT finding, verification, dan reopen.
- Upload evidence UAT.
- PIC UAT rework dan submit retest.

## 5. Frontend

- IT Lead: halaman `/itlead/uat-assignment` untuk queue dan assignment UAT.
- Requester: UAT workspace berbasis ticket ID di `/user/uat?ticket_id={id}`.
- Requester dapat membuat scenario, menjalankan test run, mencatat hasil, membuat finding, mengunggah evidence, dan menyelesaikan UAT.
- PIC: UAT finding rework dan submit UAT retest pada PIC Internal Testing workspace.
- Ticket detail dan Development Monitoring menampilkan status UAT.

## 6. Hasil Verifikasi

- Backend PHPUnit: **98 tests / 810 assertions — Passed**.
- Phase 10 PHPUnit: **26 tests / 65 assertions — Passed**.
- Laravel Pint: **Passed**.
- Frontend TypeScript check: `corepack pnpm typecheck` — **Passed**.
- Frontend production build: `corepack pnpm build` — **Passed**.
- Vite masih memberikan warning ukuran JavaScript bundle di atas 500 kB; tidak memblokir build.

## 7. HTTP E2E

HTTP E2E melalui server Laravel aktual lulus **35 checks**. Flow mencakup:

- IT Lead queue, assignment requester, request ID, dan duplicate assignment `409`.
- Requester/QA isolation `403`.
- Start UAT, scenario, run, rejected result validation, finding, dan rejected completion.
- Dua status history failure: `uat_in_progress → uat_failed → development_in_progress`.
- PIC start/resolve finding, worklog rework, internal test passed, dan submit retest.
- Requester retest, cycle increment, finding verification, seluruh scenario accepted, dan `uat_approved`.
- Final response request ID.

## 8. Browser Verification

Ephemeral Playwright/Chrome verification lulus:

- Desktop `1440 × 900`.
- Mobile `390 × 844`.
- IT Lead UAT assignment queue.
- Requester UAT workspace.
- Tidak ada horizontal overflow utama.
- Tidak ada application console error; `401` bootstrap dan favicon `404` diabaikan sebagai expected/non-application resources.

## 9. Status Dokumentasi & Rilis

Dokumentasi Phase 10 telah diperbarui pada:

- `README.md`
- `docs/database-design.md`
- `docs/api-contract.md`
- `docs/role-permission-matrix.md`
- `docs/implementation-roadmap.md`
- `docs/phase-10-uat-requester-signoff.md`

Hasil rilis yang tervalidasi:

- Frontend install frozen lockfile, typecheck, format check, dan production build: **Passed**.
- Backend PHPUnit: **98 tests / 810 assertions — Passed**.
- Phase 10 PHPUnit: **26 tests / 65 assertions — Passed**.
- Laravel Pint: **Passed**.
- HTTP E2E aktual: **35 checks — Passed**.
- Browser verification desktop `1440 × 900` dan mobile `390 × 844`: **Passed**.

## 10. Database, Route, and Worktree

- Fresh migration and seed: **12 migrations — all Ran**.
- API route count: **160 routes**.
- No `package-lock.json` was created; frontend remains pnpm-based.
- `AGENTS.md`, local environment files, database files, logs, cache, upload storage, build output, browser screenshots, evidence files, and temporary artifacts are excluded from the Phase 10 commit.

Status commit:

- Commit message: `feat: implement UAT execution rework and requester sign-off`.
- Commit hash: `c72a836da7d0f90ec9a4ec986f1eef9f3aeb514b`.
- Phase 10 source, migration, tests, frontend, and documentation are included in the commit.

Phase 11 scope is **Approval and Release Preparation**. It is not started in this closure and must not be inferred from the `uat_approved` sign-off state.

Implementasi dan verifikasi Phase 10 selesai. Release approval, deployment, monitoring, notification, dan Knowledge Base tetap ditunda ke phase berikutnya sesuai scope.
