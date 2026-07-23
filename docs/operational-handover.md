# Operational Handover

## Application overview

Tic Hub is an internal React SPA and Laravel API for ticket lifecycle, reporting, notifications, alerts, and Knowledge Base resolution reuse. Repository: `[approved repository URL]`.

## Ownership

| Responsibility | Owner |
| --- | --- |
| Business application | `[TBD]` |
| Support/on-call | `[TBD]` |
| Deployment | `[TBD]` |
| Database | `[TBD]` |
| Scheduler | `[TBD]` |
| Backup/restore | `[TBD]` |
| Monitoring/logging | `[TBD]` |
| Security incident escalation | `[TBD]` |

Common tasks include user activation, role-approved master data, SLA scanners, failed-job inspection if queues are enabled, health checks, backup verification, log correlation by request ID, and release rollback. Use the dedicated runbooks; never expose credentials or bypass backend authorization.

Known limitations and open risks are maintained in `docs/known-issues-and-risks.md`. Phase 16 closes only after CI passes, staging infrastructure and secure secrets exist, restore/performance/full-role UAT evidence is recorded, owners are assigned, and business/IT approvals are signed.
