# Phase 11 — Approval Workflow and Release Preparation

## Scope

Phase 11 extends `uat_approved` through business/technical approval and release preparation without executing deployment:

`uat_approved → approval_pending → release_preparation → release_ready`

Rejection records both transitions:

`approval_pending → approval_revision → development_in_progress`

Deployment, rollback execution, production monitoring, closure, notifications, Knowledge Base, CI/CD, and infrastructure integrations remain out of scope.

## Data Model

Migration `2026_07_17_150000_create_release_approval_tables` adds ticket approval/release summary fields and creates:

- `ticket_approval_requests`
- `ticket_approval_steps`
- `ticket_approval_action_histories`
- `ticket_release_plans`
- `ticket_rollback_plans`
- `release_checklist_templates`
- `ticket_release_checklist_items`
- `ticket_release_checklist_histories`

Approval requests retain cycles; approval steps use optimistic versions; plans retain versions and lock versions; approval/checklist histories are append-only. Checklist templates are seeded in the migration and instantiated per release plan.

## Approval

- IT Lead requests approval only from `uat_approved`.
- The business step is assigned to an active Manager in the requester division.
- The technical step is assigned to an active IT Lead.
- Only the assigned approver can decide a pending step.
- Duplicate/stale decisions return HTTP `409`.
- Both steps must approve before `release_preparation` starts.
- A rejection requires plain-text notes, cancels pending steps, lowers progress to at most 90, and sends the ticket back through development/testing/UAT.

## Release Preparation

IT Lead or the active PIC assignment can create versioned release and rollback plans. Release plans validate affected components, validation steps, monitoring steps, release owner role/activity, downtime consistency, and the proposed release window. Rollback plans validate trigger, steps, validation, responsible user, and data recovery strategy when migration is required.

The final `release_ready` gate requires approved business and technical steps, approved release/rollback plans, active release owner, complete required checklist, no blocked item, no open QA/UAT finding, and no active internal/QA/UAT run. No `release_ready → deployed` transition exists.

## Permissions and Redaction

- Manager receives business summary and assigned decision actions, not technical plan/evidence.
- IT Lead manages requests, technical decisions, plan reviews, checklist, owner, and readiness.
- PIC contributes plans/evidence/checklist only for an active ticket assignment.
- Requester/Supervisor receive general approval/release status only.
- Executive remains aggregate/read-only and cannot open technical preparation endpoints.
- Attachment resources never expose disk paths or stored names.

## API Groups

- IT Lead: approval request queue, technical queue/decision, release/rollback plans, checklist, and readiness confirmation.
- Manager: business approval queue/detail/decision.
- PIC: assigned release preparation, plan/rollback contribution, and evidence upload.
- Admin: release checklist template list/create/update/deactivate.

All nested resources validate parent ticket ownership and all API envelopes retain request IDs.

## Verification

Phase 11 feature coverage includes request/duplicate behavior, role/approver ownership, rejection reason, two explicit rejection transitions, dual approval completion, release/rollback validation, nested ownership, stale checklist updates, blocked readiness, successful readiness, and duplicate readiness.

Final migration count, route count, full test totals, HTTP E2E, browser verification, and commit hash are recorded during closure.

## Phase 12

Phase 12 remains deferred. It must not execute deployment or rollback from the preparation records created here. No deployment, rollback execution, monitoring, closure, production notification, Knowledge Base, CI/CD, GitHub, or Vercel integration is included in Phase 11.
