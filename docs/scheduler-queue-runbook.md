# Scheduler and Queue Runbook

## Current behavior

Laravel schedules `tickets:scan-sla-alerts` and `tickets:scan-inactivity` every 15 minutes. Both use a 20-minute overlap lock and `onOneServer()`. The lock store must be shared by all application nodes. Current notification delivery is synchronous; no application job currently requires a persistent queue worker.

## Production scheduler

Run exactly one scheduler invocation each minute under a supervised service account:

```cron
* * * * * cd <release>/backend && php artisan schedule:run >> /dev/null 2>&1
```

Alternative process managers may run `php artisan schedule:work`. Do not run both mechanisms. Confirm `APP_TIMEZONE=Asia/Jakarta`, a shared cache store, writable logs, and database connectivity.

## Verification

```bash
php artisan schedule:list
php artisan tickets:scan-sla-alerts
php artisan tickets:scan-sla-alerts
php artisan tickets:scan-inactivity
php artisan tickets:scan-inactivity
```

The second scanner run must create no duplicate alert for the same eligibility window. A nonzero command exit code is an operational failure. Monitor last successful run, duration, scanned count, created count, and failure count.

## Queue readiness

If a future approved change implements `ShouldQueue`, define worker count, `--timeout`, `--tries`, backoff, after-commit dispatch, failed-job alerting, and graceful restart before deployment. Recommended supervised command template:

```bash
php artisan queue:work database --sleep=3 --tries=3 --timeout=90 --max-time=3600
```

Inspect failures using `php artisan queue:failed`; retry only after root cause review. Phase 16 does not introduce asynchronous delivery or require a production worker.
