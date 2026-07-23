# Deployment Runbook

1. Confirm approved commit, change window, owners, CI results, dependency audit, security review, and human UAT sign-off.
2. Verify production secrets are supplied by the approved secret manager; never read or print them in deployment logs.
3. Create and verify database/private-storage backups using `docs/backup-restore-runbook.md`.
4. Decide whether maintenance mode is required based on migration compatibility and write duration.
5. Fetch the immutable release artifact or approved commit without rewriting repository history.
6. Install backend dependencies: `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`.
7. Build frontend from its frozen lockfile: `corepack pnpm install --frozen-lockfile && corepack pnpm build`.
8. Validate environment: production mode, debug off, APP_KEY present, HTTPS URLs, secure cookie, exact CORS/Sanctum domains, non-disposable MySQL, durable private storage, logging, scheduler, and optional queue.
9. Run `php artisan migrate --force`; stop and invoke rollback decision if it fails.
10. Run `php artisan optimize:clear && php artisan config:cache && php artisan route:cache`.
11. Verify least-privilege storage permissions and private evidence access.
12. Start/reload web and scheduler processes; restart queue workers only if queued jobs are enabled.
13. Verify `/up` and `/api/health` without exposing internal details.
14. Smoke test login, role authorization, ticket list/detail, notification ownership, Knowledge Base visibility, report scope, evidence download, and logout.
15. Verify deployed commit, static asset loading, direct SPA deep links, request IDs, and security headers.
16. Trigger rollback for health failure, migration error, elevated 5xx, authorization leakage, data corruption, or critical security regression.
17. Follow `docs/rollback-runbook.md`; do not run ad hoc Git reset commands.
18. Communicate outcome, known issues, and operator ownership.
19. Monitor errors, latency, scheduler, database, storage, and business-critical flows through the agreed observation window.

This runbook is planning guidance. Phase 16 does not perform an actual production deployment.
