# Implementation Roadmap

## Delivery rules

Setiap fase kecil, memiliki acceptance criteria, migration/test sendiri, dan tidak mengubah visual utama kecuali untuk portability, responsive behavior, accessibility, atau state/error yang diperlukan. Jangan memulai fase berikutnya bila build/test fase aktif belum hijau. Dummy data diganti per vertical slice, bukan dengan rewrite frontend.

## Phase 0 — Decisions and baseline (documentation only/current audit)

**Output:** enam dokumen audit/desain ini.

Konfirmasi business decisions: identity topology, multi-role, approval path, working calendar/pause rules, division scope, close/reopen policy, attachment storage, notification channels, dan executive drill-down. Keputusan yang tidak memblokir portability boleh ditunda.

## Phase 1 — Make frontend portable and verifiable

**Scope:** hapus hard dependency Figma dari build, pindahkan metadata shell ke config portable, deklarasikan pnpm/Node policy, tambah `.gitignore` dan `.env.example`, perbaiki 27 TypeScript diagnostics dan formatting, tambah scripts `typecheck`/`check`, serta minimal smoke test setup.

**Acceptance:** fresh `pnpm install --frozen-lockfile`, type-check, test, format check, dan `pnpm build` hijau; dev server dapat dibuka tanpa `.figma`; route penting termuat. Tidak ada perubahan visual yang disengaja.

## Phase 2 — Responsive/accessibility shell and route integrity

**Scope:** sidebar mobile drawer, responsive header/filter/action/form grid, modal accessibility, keyboard-accessible interactive rows/cards, labels/ARIA, 403/404/error boundaries, route IDs untuk ticket-specific forms, neutral ticket detail route, lazy loading bila bermanfaat.

**Acceptance:** manual/browser verification desktop dan 320/375/768 px untuk login, dashboard, list, detail, modal, dan form; keyboard/modal checks; deep-link fallback documented/tested. Dummy data tetap boleh digunakan.

## Phase 3 — Laravel backend skeleton and CI

**Scope:** buat `backend/` Laravel REST API, MySQL test config, Sanctum, API v1 convention, health endpoint, error envelope/request ID, base User/Division/Role/Permission/Application migrations, factories, seeders non-production, Pint, static analysis, PHPUnit/Pest choice, CI jobs.

**Acceptance:** migrations pada disposable MySQL, formatter/static analysis/tests hijau; no secrets committed; `.env.example` lengkap; frontend/backend commands documented.

## Phase 4 — Authentication and RBAC foundation

**Scope:** Sanctum CSRF/login/logout/me, session restore frontend, authenticated/forbidden routes, database roles/permissions, policies/query scopes, remove role switcher from production (may keep behind explicit dev flag), executive projection baseline.

**Acceptance:** feature tests seluruh role untuk login and access boundaries; refresh retains session; inactive user denied; executive cannot retrieve technical fields; UI navigation derives from permissions.

## Phase 5 — Ticket read model and master data

**Scope:** ticket/application/division/tag/status history/activity schema, list/detail APIs, pagination/filtering, frontend API client/server state, loading/error/empty, replace dummy ticket list/detail/dashboard portions incrementally.

**Acceptance:** policy-scoped lists for own/division/assigned/global; invalid ID is 404; cross-role/cross-division tests; frontend list/detail and key dashboards read API without visual rewrite.

## Phase 6 — Ticket creation, attachments, and supervisor validation

**Scope:** create/edit revision/cancel ticket, private attachment upload/download, ticket numbering/idempotency, supervisor validation queue and validated/revision/rejected transitions, activity/audit events, in-app notification.

**Acceptance:** full Requester → Supervisor vertical slice E2E; validation and file security tests; retries do not duplicate tickets; queue changes after action; audit/history persisted.

## Phase 7 — Triage, assignment, analysis, and planning

**Scope:** IT Lead triage/priority/SLA policy selection, PIC assignment/transfer, analysis/RCA, wait/resume states, implementation plan/work log, optimistic concurrency.

**Acceptance:** transition-map and eight-role denial tests; concurrent action returns 409; assignment/history/notification correct; PIC workspace uses API.

## Phase 8 — SLA engine and escalation

**Scope:** business calendar/hours/holidays, default 4h/8h/2d/5d policies, SLA clocks/pause intervals, scheduler warnings/breaches, escalation rules/events, monitoring API/UI, admin policy UI integration.

**Acceptance:** deterministic unit tests across weekends, holidays, timezone, pause/resume, policy changes, breach idempotency; UI no longer uses static remaining hours; notification delivery queued.

## Phase 9 — Development and testing lifecycle

**Scope:** development completion evidence, internal test runs, QA runs/cases, defects/retests, UAT assignment and repeatable runs. Refactor hard-coded test cases into ticket-specific records/templates.

**Acceptance:** failed test creates revision path; retest history preserved; only assigned actors submit; UAT URL contains ticket/run ID; E2E to approval-ready state.

## Phase 10 — Approval, deployment, monitoring, and closure

**Scope:** approval rules/queue/history, separation of duties, deployment plan/checklist/window/rollback/evidence, deploy result, post-deploy monitoring, closure/resolution/requester confirmation/reopen.

**Acceptance:** happy path and revision/reject/rollback/reopen alternatives tested; no credentials in records/log; state transition, audit, notification, and SLA completion are transactional.

## Phase 11 — Notifications and audit operations

**Scope:** notification preferences/templates, email delivery, retry/failure visibility, mark-read persistence, complete audit search/export/retention controls. Add real-time only if polling is insufficient.

**Acceptance:** event/channel preference tests, idempotent delivery, authorized audit access, sensitive value redaction, failed-job operational procedure.

## Phase 12 — Knowledge Base

**Scope:** categories, article draft/review/publish/archive/versioning, search, ticket linking, closure suggestion, feedback. Preserve executive-safe/publication visibility rules.

**Acceptance:** author/reviewer/publisher policy tests, published-only reader view, version history immutable, ticket-to-article link and search E2E.

## Phase 13 — Reporting, hardening, and production readiness

**Scope:** accurate role dashboards/exports, performance/index review, accessibility audit, security test, dependency scan, observability, backup/restore drill, retention, runbooks, load test, staging UAT, rollout/rollback plan.

**Acceptance:** CI/CD green; authorization matrix evidence; responsive/browser coverage; performance budget met; backup restored successfully; security findings resolved/accepted; production sign-off.

## Recommended first implementation sequence

```text
Portable build
→ responsive/accessibility/routing baseline
→ Laravel + CI
→ Sanctum + RBAC
→ ticket read APIs
→ create/validation vertical slice
→ triage/assignment/work
→ SLA/escalation
→ testing/UAT
→ approval/deployment/monitoring/close
→ notification/audit maturity
→ Knowledge Base
→ hardening/release
```

## Cross-cutting definition of done

Untuk setiap phase yang mengubah frontend: install, format check, type-check, automated tests, production build, dan desktop/mobile route verification. Untuk backend: migration pada test MySQL, formatter, static analysis, automated feature/unit tests, dan authorization test untuk semua role terdampak. Dokumentasikan file changed, commands/results, environment/migration notes, API contract update, remaining work, dan rollback consideration. “UI menampilkan toast” tidak dianggap feature selesai tanpa persistence dan verification.

## Main technical risks and mitigations

| Risiko | Mitigasi |
|---|---|
| Prototype status/SLA berbeda dari business workflow | Finalize canonical enums/state map before ticket migrations |
| UI regressions saat integrasi | Screenshot/reference routes, incremental feature adapters, responsive smoke tests |
| RBAC data leak, terutama Executive | Query scopes + API Resources + matrix feature tests; deny by default |
| SLA working-time arithmetic salah | Dedicated service, immutable clock snapshot, exhaustive calendar tests |
| Double transition/concurrent approvals | Row lock, version precondition, idempotency keys, after-commit events |
| Attachment malware/data exposure | Private storage, validation, scan status, parent policy on download |
| Notification duplication | Unique event keys/outbox-like after-commit dispatch and retry policy |
| Scope expansion into full CRM sales/customer features | Treat as separate bounded-context discovery; current prototype is ITSM-centric |
| Big-bang rewrite | Vertical slices and reviewable phases; preserve existing components/routes until replacement |
| Environment drift | pinned toolchain, `.env.example`, CI disposable MySQL, documented local setup |
