# Backup and Restore Runbook

## Scope and ownership

This runbook covers the MySQL database and private ticket evidence. Production backups are executed only by the designated Database Owner and Storage Owner. Names remain `[TBD]` until approved. Backup data must be encrypted in transit and at rest by the selected infrastructure provider.

Initial targets pending infrastructure approval:

- RPO: 24 hours.
- RTO: 4 hours.
- Retention: 14 daily, 8 weekly, and 12 monthly restore points.
- Naming: `tic-hub_<environment>_<UTC timestamp>_<release>.sql.gz` and a matching evidence manifest.

## Pre-deployment backup

1. Confirm the environment and approved release window.
2. Quiesce writes or record the transaction-consistent backup mechanism.
3. Create a MySQL dump without placing a password on the command line:

```bash
mysqldump --defaults-extra-file=<protected-client-config> --single-transaction --routines --triggers --set-gtid-purged=OFF <database> | gzip > <backup-file>.sql.gz
```

4. Snapshot/version the private evidence storage using provider-native tooling.
5. Generate SHA-256 checksums and store them separately from the backup.
6. Record database version, schema migration, application commit, operator, timestamp, and object-storage snapshot/version.
7. Verify the dump is non-empty and checksum validation passes.

## Restore verification

Restore only to an isolated staging database first:

```bash
gzip -dc <backup-file>.sql.gz | mysql --defaults-extra-file=<protected-client-config> <staging-restore-database>
```

Then:

1. Restore private evidence to an isolated bucket/path.
2. Run `php artisan migrate:status` without applying migrations.
3. Run health checks and a read-only smoke test for authentication, ticket detail, reports, and evidence authorization.
4. Recalculate and compare table counts, checksums, critical sample tickets, and evidence manifests.
5. Delete or securely expire staging restore data after approval.

## Rollback decision point

Restore is justified only when forward-fix and application rollback cannot preserve data integrity. Database restore loses writes after the chosen recovery point and therefore requires Incident Commander, Database Owner, Business Owner, and IT Release Approver authorization. Never run `migrate:rollback` or restore a dump in production without an approved impact assessment.

## Drills

Perform a staging restore drill at least quarterly and before first production release. Record duration, achieved RPO/RTO, missing evidence, and remediation. No production backup or restore was executed during Phase 16.
