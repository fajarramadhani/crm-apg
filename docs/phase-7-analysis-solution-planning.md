# Phase 7 — PIC Analysis, RCA, and Solution Planning

## Delivered workflow

Phase 7 implements the production path:

```text
assigned -> analysis -> solution_planning -> plan_review
                                      ^          |
                                      | revision |
                                      +----------+
                                                 |
                                                 +-> ready_for_development
```

Only the current assigned PIC can start and edit analysis or planning. Analysis and plan changes are explicit saves. Completing analysis requires the RCA fields; submitting a plan requires ordered implementation steps, effort, risk, and testing details. IT Lead can request a reasoned revision or approve the submitted plan.

## Data integrity and audit

- Analysis and solution plans have a per-ticket version plus `lock_version` optimistic concurrency guard.
- Stale saves, duplicate completion/submission/approval, and invalid state transitions return `409`.
- A revision preserves the reviewed plan and requires creation of a new current version.
- Mutations use database transactions and ticket row locks, persist status history, and dispatch analysis/plan lifecycle events.
- Ticket summary timestamps/current foreign keys provide an efficient read model without deleting prior versions.

## Frontend integration

- `/pic/workspace` now loads assigned tickets from the API and provides analysis, RCA, ordered implementation steps, effort/risk, rollback/testing/deployment, revision context, and confirmation modals.
- `/pic/rca` redirects to the canonical workspace.
- `/itlead/plan-review` provides a filterable queue, RCA/plan detail, effort in hours and working days, risk, attachments/history, and revision/approval actions.
- Requester ticket detail shows only generic progress messages. Internal RCA, technical impact, solution, risk, rollback, testing, review notes, and Phase 7 history metadata are redacted.

## Verification evidence

- Backend feature coverage includes happy path, validation, every-role authorization boundaries, wrong-PIC ownership, stale lock conflicts, duplicate actions, version preservation, history/events, filter/pagination, and requester redaction.
- The Sanctum cookie-session E2E exercised PIC start/RCA/complete/submit, IT Lead revision, PIC version 2/resubmit, IT Lead approval, duplicate-approval conflict, wrong-PIC and Executive denial, request IDs, and requester-safe output.
- Browser verification covered populated PIC and IT Lead pages, desktop and 390×844 layouts, absence of horizontal overflow, and no console warnings/errors.

## Phase 8 boundary

Phase 8 begins at `ready_for_development`: development execution, work evidence, internal test runs/cases, and the transition toward QA. It must reuse Phase 7's approved plan and preserved versions rather than reopening or duplicating analysis/review behavior.
