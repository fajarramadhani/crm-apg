# Implementation Roadmap

## Phase 11 delivered scope

Business approval, technical readiness, approval revision, versioned release/rollback plans, checklist templates/items/history, internal evidence, release ownership, and the `release_ready` gate are implemented without deployment execution. See `docs/phase-11-approval-release-preparation.md`.

## Phase 10 delivered scope

UAT assignment queue, requester-owned UAT scenarios/runs/results, rejected-run rework, immutable UAT finding history, PIC rework gates, retest cycles, evidence authorization, requester sign-off, and responsive UAT workspaces are implemented and verified. See `docs/phase-10-uat-requester-signoff.md`.

## Phase 9 delivered scope

QA assignment queue and workload charts, testing dashboard, test case/run/result execution, inline defect creation with immutable defect history logs, PIC defect rework, internal validation test gates, QA retesting cycles, and requester/executive technical data redactions are fully implemented and verified.

## Phase 8 delivered scope

Development execution, actual-effort worklogs, progress snapshots, private evidence, internal test case/run/result persistence, failure/rework/retest, Ready for QA, PIC UI, IT Lead monitoring, requester redaction, events, and feature coverage are implemented.

## Current delivery numbering

Phase 7 is now implemented as **PIC Analysis, RCA, and Solution Planning**. Phase 8 is implemented as **Development Execution and Internal Testing**. Phase 9 is implemented as **QA Assignment, Execution, Defect Rework, and Retests**. Phase 10 is implemented as **UAT Execution, Requester Revision, and Sign-off**. Release approval, deployment, monitoring, notifications, and Knowledge Base remain later phases.

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

## Phase 7 — PIC analysis, RCA, and solution planning (implemented)

**Scope:** PIC-owned analysis/RCA, explicit draft saves and completion, versioned solution plans, estimated effort/risk, IT Lead review/revision/approval, history/events, optimistic concurrency, and requester-safe progress.

**Acceptance:** implemented with policy/permission tests, ownership denial, stale-write and duplicate-action `409` responses, retained plan versions, history/event dispatch, real PIC/IT Lead UI, and responsive browser verification.

## Phase 8 — Development execution and internal testing

**Scope:** development start/progress/completion evidence, work logs, internal test runs and cases, failure/revision handling, and the transition toward QA. Continue using Phase 6 SLA snapshots; broader escalation automation remains a later bounded slice.

**Acceptance:** only assigned actors can record work; evidence and test history are retained; failed internal tests create an authorized revision path; duplicate transitions are rejected; frontend and API complete an E2E path to QA-ready state.

## Phase 9 — Development and testing lifecycle

**Scope:** development completion evidence, internal test runs, QA runs/cases, defects/retests, UAT assignment and repeatable runs. Refactor hard-coded test cases into ticket-specific records/templates.

**Acceptance:** failed test creates revision path; retest history preserved; only assigned actors submit; UAT URL contains ticket/run ID; E2E to approval-ready state.

## Phase 10 — UAT execution, requester revision, and sign-off (implemented)

**Scope:** IT Lead UAT assignment, requester-owned scenarios/runs/results, rejected UAT rework, finding lifecycle/history, PIC rework gates, UAT retests, evidence authorization, and requester sign-off.

**Acceptance:** accepted and rejected UAT paths, duplicate/stale conflicts, role boundaries, cross-ticket nested resource rejection, evidence redaction, HTTP E2E, and desktop/mobile browser verification are tested. Release approval and deployment are explicitly not started.

## Phase 11 — Approval workflow and release preparation (implemented)

**Scope:** two-step approval, rejection/revision, versioned release and rollback planning, release checklist, evidence, release owner, and final release readiness.

**Acceptance:** role boundaries, optimistic/pessimistic concurrency, nested ownership, readiness blockers, redaction, HTTP E2E, responsive browser checks, and no deployment execution.

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

| Risiko                                                | Mitigasi                                                                                     |
| ----------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Prototype status/SLA berbeda dari business workflow   | Finalize canonical enums/state map before ticket migrations                                  |
| UI regressions saat integrasi                         | Screenshot/reference routes, incremental feature adapters, responsive smoke tests            |
| RBAC data leak, terutama Executive                    | Query scopes + API Resources + matrix feature tests; deny by default                         |
| SLA working-time arithmetic salah                     | Dedicated service, immutable clock snapshot, exhaustive calendar tests                       |
| Double transition/concurrent approvals                | Row lock, version precondition, idempotency keys, after-commit events                        |
| Attachment malware/data exposure                      | Private storage, validation, scan status, parent policy on download                          |
| Notification duplication                              | Unique event keys/outbox-like after-commit dispatch and retry policy                         |
| Scope expansion into full CRM sales/customer features | Treat as separate bounded-context discovery; current prototype is ITSM-centric               |
| Big-bang rewrite                                      | Vertical slices and reviewable phases; preserve existing components/routes until replacement |
| Environment drift                                     | pinned toolchain, `.env.example`, CI disposable MySQL, documented local setup                |
