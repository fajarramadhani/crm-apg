# System Architecture

## Prinsip arsitektur

1. Pertahankan React UI, route intent, visual hierarchy, dan komponen reusable; lakukan refactor incremental.
2. Laravel menjadi authority untuk identity, authorization, workflow transition, SLA, audit, dan data.
3. Frontend tidak boleh menentukan permission hanya dari role atau menyimpulkan transition sendiri.
4. Workflow transition harus eksplisit, transactional, idempotent bila relevan, dan menghasilkan history/audit/notification.
5. Executive memakai resource/serializer terpisah atau field policy yang hanya mengembalikan data ringkas.
6. Waktu disimpan UTC, ditampilkan `Asia/Jakarta`, dan SLA dihitung memakai kalender kerja yang dikonfigurasi.
7. Gunakan modular monolith Laravel dahulu; microservice tidak dibutuhkan pada skala/kematangan saat ini.

## Struktur repository target

```text
/
├── frontend/
│   ├── src/
│   │   ├── app/              # router, providers, auth bootstrap
│   │   ├── api/              # HTTP client dan generated/manual contracts
│   │   ├── components/       # design-system/reusable UI yang ada
│   │   ├── features/         # tickets, sla, approvals, deployments, kb, admin
│   │   ├── pages/            # route-level composition
│   │   ├── hooks/
│   │   ├── lib/
│   │   └── types/
│   ├── tests/
│   └── .env.example
├── backend/
│   ├── app/
│   │   ├── Actions/          # use-case/transition actions
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Http/{Controllers,Requests,Resources}/Api/V1
│   │   ├── Jobs/
│   │   ├── Models/
│   │   ├── Notifications/
│   │   ├── Policies/
│   │   ├── Services/         # SLA/calendar/workflow services
│   │   └── Support/
│   ├── database/{factories,migrations,seeders}/
│   ├── routes/api.php
│   ├── tests/{Feature,Unit}/
│   └── .env.example
└── docs/
```

Pemindahan frontend ke `frontend/` sebaiknya dilakukan hanya setelah build portable stabil agar perubahan path mudah direview. Alternatif fase awal: pertahankan frontend di root, buat `backend/`, lalu pindahkan pada fase housekeeping terpisah.

## Frontend target

### Runtime layers

- **App shell:** router, authenticated session bootstrap, error boundary, 403/404, layout responsive.
- **API client:** base URL dari `VITE_API_BASE_URL`, `credentials: include`, CSRF bootstrap Sanctum, normalized error format, abort signal, upload support.
- **Server state:** pilih satu pendekatan konsisten. Rekomendasi TanStack Query saat fase integrasi dimulai karena pagination, invalidation, loading/error, dan mutation concurrency akan dominan. Ini dependency baru dan harus disetujui/dijelaskan pada fase tersebut.
- **Local UI state:** modal, tab, draft form sementara; bukan ticket/server truth.
- **Feature modules:** masing-masing memiliki API functions, query keys, schema/types, page components, dan permission-aware actions.
- **Forms:** validasi client untuk UX, tetapi Laravel Form Request tetap authority.

### Routing target

Gunakan route netral berbasis resource, misalnya `/tickets/:ticketId`, bukan `/user/tickets/:id`. Queue tetap role/task-specific: `/validation`, `/triage`, `/qa`, `/approvals`, `/deployments`, `/monitoring`. Semua route berada di bawah authenticated shell. Loader/session mengarahkan 401 ke login, policy response 403 ke forbidden, dan resource missing ke 404.

URL halaman kerja harus membawa ID: `/tickets/:id/analysis`, `/tickets/:id/testing/internal`, `/tickets/:id/testing/qa`, `/tickets/:id/uat`, `/tickets/:id/approval`, `/tickets/:id/deployment`, `/tickets/:id/monitoring`, `/tickets/:id/close`.

### Permission rendering

Backend mengirim `permissions` global user dan `allowed_actions` per ticket. Frontend memakai keduanya untuk menyembunyikan/disable action dan menjelaskan alasan, tetapi setiap mutation tetap diperiksa policy di backend. Executive memakai endpoint summary atau resource projection yang tidak memuat RCA detail, internal comment, raw log, attachment sensitif, deployment secret, atau PII yang tidak perlu.

## Backend target

### Laravel REST API

- API versioned pada `/api/v1`.
- Controller tipis; Form Request menangani validation/authorization awal; Policy menangani resource authorization; Action class menangani use case.
- API Resource menentukan bentuk response dan redaction.
- Sanctum SPA cookie authentication untuk frontend internal pada trusted first-party domains; konfigurasi stateful domains, CORS, CSRF cookie, HTTPS, secure/same-site cookie wajib per environment.
- Role/permission disimpan di database. Implementasi dapat memakai tabel internal lebih dahulu; dependency RBAC pihak ketiga hanya ditambah jika kebutuhan multi-role/permission administration membenarkannya.
- Queue worker menjalankan notification delivery, escalation, export, dan pekerjaan non-interaktif.
- Scheduler menjalankan SLA warning/breach evaluation dan cleanup.

### Workflow engine

Model status sebagai state machine yang dikendalikan backend. `tickets.status` menyimpan current state; `ticket_status_histories` adalah append-only history. Transition action harus:

1. lock ticket row (`SELECT ... FOR UPDATE`);
2. memeriksa current state, permission, required fields, dan version;
3. menjalankan perubahan domain dalam transaction;
4. membuat status history, activity, dan audit event;
5. memperbarui SLA clock bila transition pause/resume/complete;
6. dispatch domain event setelah commit untuk notification/escalation.

Gunakan optimistic concurrency melalui `version` atau `updated_at` precondition agar dua actor tidak melakukan transition yang saling menimpa.

### SLA service

SLA bukan sekadar `deadline = created + hours`. Service membutuhkan priority policy, working schedules, holidays, timezone, start event, pause states, warning thresholds, breach timestamp, dan resolution timestamp. Default:

- Critical: 4 working hours
- High: 8 working hours
- Medium: 2 working days
- Low: 5 working days

`over_sla` adalah derived flag/event, bukan primary workflow status. Waiting User dan Waiting External Party dapat pause clock hanya jika kebijakan bisnis mengizinkan; keputusan ini perlu dikonfirmasi.

## Integrasi proses

```text
React SPA
  -> Sanctum CSRF/session
  -> Laravel API v1
       -> Policies + Form Requests
       -> Domain Actions / Workflow / SLA
       -> MySQL transactions
       -> after-commit Events
            -> Queue: notifications, escalations, exports
            -> Audit/activity append-only records
       -> Scheduler: SLA warning/breach and monitoring reminders
```

Notifikasi in-app disimpan di database. Email menjadi channel awal yang wajar; WhatsApp/SMS jangan diasumsikan sebelum provider, consent, template, biaya, dan security disetujui. Real-time WebSocket dapat ditunda; polling/refetch sudah cukup untuk fase awal.

## Security dan operasional

- Rate limit login dan endpoint mahal; lock/disable inactive account.
- Validasi MIME, ukuran, extension; simpan attachment di private storage dengan randomized key dan signed/authorized download.
- Jangan menyimpan secret deployment di ticket fields atau log. Integrasi CI/CD memakai secret manager/environment.
- Audit log append-only dengan actor, request ID, IP, user agent, before/after redacted, dan timestamp; password/token/file content tidak dicatat.
- Logging terstruktur dengan correlation ID; health endpoint; failed job monitoring; backup/restore MySQL dan object storage.
- Pagination server-side, indexes sesuai query, N+1 prevention, API resource tests.
- CI minimal: frontend install/typecheck/test/build; backend Pint/static analysis/test; migration on disposable MySQL; secret/dependency scan.

## Testing strategy

- **Frontend unit/component:** permissions, form state, error/loading/empty, responsive components.
- **Frontend integration/E2E:** login, create ticket, validate, triage/assign, work/test/UAT/approve/deploy/monitor/close, 403, executive redaction.
- **Backend unit:** state transition map, business calendar/SLA arithmetic.
- **Backend feature:** every endpoint happy/validation/forbidden/conflict, attachment access, notification read scope.
- **Authorization matrix tests:** data-provider test untuk seluruh role × permission, termasuk cross-division/cross-assignment scope.
- **Database:** migration up/down where safe, constraints, unique/index behavior.

## Architectural decisions to record later

ADR yang dibutuhkan sebelum implementasi terkait: authentication topology (same-domain vs subdomain), multi-role user support, approval rules, working calendar/pause policy, attachment storage, notification channels, ticket numbering, Knowledge Base publishing, dan deployment integration. Keputusan tersebut tidak perlu menghambat fase portability dan CI baseline.
