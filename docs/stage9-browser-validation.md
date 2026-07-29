# Stage 9 Browser Validation

This harness renders and interacts with the Stage 9 UI in native headless Chrome and Edge through Chrome DevTools Protocol. It uses Node 24 built-ins only and adds no package dependency.

## Safety Boundary

The fixture command refuses to run unless `APP_ENV` is `local`, `testing`, or `staging`; the connection is MySQL; the database name explicitly indicates a disposable browser/test database; the exact confirmation is supplied; and `STAGE9_BROWSER_PASSWORD` contains at least 16 characters.

Fixtures use only synthetic `@stage9.invalid` identities and database notifications. Never point this command at a retained, shared, or production database.

## Setup

Create a disposable MySQL database and configure `backend/.env.stage9` for it. From `backend` in PowerShell:

```powershell
$env:APP_ENV='staging'
$env:STAGE9_BROWSER_PASSWORD='<safe-random-password-at-least-16-characters>'
php artisan migrate:fresh --env=stage9 --force
php artisan stage9:seed-browser-fixtures --env=stage9 --confirm=SEED-STAGE9-BROWSER-FIXTURES --json | Set-Content -Encoding utf8 "$env:TEMP\stage9-fixtures.json"
```

Start the backend with the same environment. Configure the frontend API URL, backend URL, CORS, and Sanctum stateful domains so the frontend origin can authenticate against that backend.

## Run

From `frontend` in the same PowerShell session:

```powershell
node scripts/stage9-browser-validation.mjs --manifest="$env:TEMP\stage9-fixtures.json" --frontend-url=http://localhost:5173 --backend-url=http://localhost:8000 --evidence-dir="$env:TEMP\stage9-browser-evidence"
```

Equivalent package command:

```powershell
pnpm stage9:browser -- --manifest="$env:TEMP\stage9-fixtures.json" --frontend-url=http://localhost:5173 --backend-url=http://localhost:8000 --evidence-dir="$env:TEMP\stage9-browser-evidence"
```

Override browser locations with `--chrome`, `--edge`, `STAGE9_CHROME_PATH`, or `STAGE9_EDGE_PATH`. Increase page timeout with `--timeout` or `STAGE9_TIMEOUT_MS`.

The command writes sanitized PNG filenames and `summary.json`, prints the same JSON summary, and returns nonzero when a critical check or browser launch fails.

## Limitations

- Coverage is limited to the installed Chromium engines in Chrome and Edge.
- Screenshots contain only synthetic fixture content, but they are evidence rather than pixel-diff baselines.
- The requester create form is rendered and inspected; the harness does not upload or retain a new attachment.
- The real self-approval modal is opened and its exact warning and disabled confirmation are checked, but the fixture is deliberately not approved.
