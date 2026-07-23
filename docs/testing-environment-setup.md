# Testing Environment Setup

This procedure prepares a clean testing or staging environment without shared accounts, sample tickets, or example organization data.

## Environment

Set environment-specific database, HTTPS, cookie, CORS, storage, and mail values. Use `APP_ENV=staging` for shared pre-production testing. Never use `local` or `testing` for a persistent shared environment.

At minimum, verify:

```text
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://<testing-api-host>
FRONTEND_URL=https://<testing-frontend-host>
SESSION_SECURE_COOKIE=true
DB_CONNECTION=mysql
MAIL_FROM_ADDRESS=<approved-sender>
```

## Initialize

Apply migrations and seed only canonical authorization roles:

```bash
php artisan migrate --force
php artisan db:seed --class=RoleSeeder --force
```

The default `DatabaseSeeder` also contains only canonical roles. Organization records, applications, calendars, SLA policies, tickets, and users are intentionally not created automatically.

## Initial Administrator

Provide a strong one-time password through a protected environment variable. Do not put it in shell history, source control, deployment arguments, or general logs.

```bash
export TIC_HUB_BOOTSTRAP_PASSWORD='<secret-from-approved-password-manager>'
php artisan users:provision-testing \
  --name='<accountable operator name>' \
  --email='<accountable operator email>' \
  --role=admin
unset TIC_HUB_BOOTSTRAP_PASSWORD
```

The command:

- runs only in `local`, `testing`, or `staging`;
- requires a password of at least 12 characters;
- never prints the credential;
- refuses to overwrite or reactivate an existing user;
- is disabled in production.

Use the Admin master-data pages to create approved divisions, branches, applications, calendars, priorities, and SLA policies. Additional named testing users may then be provisioned with `--division=<code>` and `--branch=<code>`.

## Data Rules

- Use named, accountable testing identities. Do not use shared role accounts.
- Do not copy local SQLite files into the environment.
- Do not load `MasterDataSeeder`; it is restricted to automated tests.
- Do not copy production personal or ticket data unless an approved anonymization process exists.
- Label any controlled UAT records in the testing database and remove them before production initialization.
- Production user provisioning requires an approved identity-management process and is not performed by `users:provision-testing`.

## Verification

Before UAT, confirm:

```text
No email ends in @tichub.local
No account name contains Demo
No record description contains Development seed
No local SQLite database is configured
No shared password is documented or distributed
All visible dashboards and queues read the API
```

Run the standard backend and frontend validation commands from the root README. A green CI run is required before the environment is accepted for testing.
