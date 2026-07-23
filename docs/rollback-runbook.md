# Rollback Runbook

## Triggers and authority

Rollback triggers include failed readiness, sustained 5xx, authorization/data exposure, migration failure, corrupted workflow state, or critical smoke/UAT failure. Incident Commander `[TBD]`, IT Release Approver `[TBD]`, and Database Owner `[TBD]` authorize the selected strategy.

## Application rollback

1. Pause new release traffic and communicate incident status.
2. Disable scheduler and pause queue workers if their actions could worsen the incident.
3. Select the previously approved immutable frontend/backend artifacts.
4. Redeploy those artifacts through the normal release mechanism. Do not use uncontrolled `git reset --hard` on a server.
5. Clear/rebuild application caches and restart supervised processes.
6. Verify `/up`, `/api/health`, login, authorization, ticket reads, and request IDs.

## Database and media

Prefer a forward fix when the new schema is backward compatible and data is valid. Roll back a migration only after confirming its `down()` is safe for production data and no new writes depend on it. If corruption or incompatibility requires restore, use the verified database and evidence recovery point from `docs/backup-restore-runbook.md`; explicitly communicate the data-loss window.

## Closure

Resume scheduler/workers only after health and data checks pass. Record timeline, decision, release hashes, database action, lost/replayed work, and follow-up defects. Keep incident communication active through the monitoring window.
