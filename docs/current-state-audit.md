# Current State Audit

## Scope and method

Audit ini dilakukan pada 14 Juli 2026 terhadap seluruh file repository yang tersedia. Tidak ada source UI yang diubah dan backend tidak dibuat. Pemeriksaan mencakup struktur file, konfigurasi Vite/TypeScript, route, halaman, komponen, data statis, event handler, state lokal, serta verifikasi instalasi, type-check, formatter, dan production build.

Repository saat ini berisi satu aplikasi frontend React; belum ada direktori Laravel, konfigurasi database, environment example, test, CI, linting, atau dokumentasi operasional. Branch aktif adalah `development`. `AGENTS.md` sudah memiliki perubahan lokal sebelum audit dan tidak disentuh oleh audit ini.

## Struktur saat ini

```text
/
├── AGENTS.md
├── CLAUDE.md                  # hanya merujuk ke AGENTS.md
├── index.html                 # shell dengan slot komentar Figma
├── package.json
├── pnpm-lock.yaml
├── tsconfig.json
├── vite.config.ts             # berisi plugin khusus Figma Make
└── src/
    ├── App.tsx                # auth/role dan seluruh route
    ├── data.ts                # semua dummy data, label, helper, KPI/chart
    ├── types.ts               # model frontend sederhana
    ├── components/
    │   ├── Layout.tsx
    │   └── ui.tsx
    ├── imports/
    │   └── apg_crm_prototype_walkthrough_v2.pdf
    └── pages/                 # 27 page component
```

## Konfigurasi Figma Make dan portability

| Temuan | Dampak | Prioritas |
|---|---|---|
| `vite.config.ts` mengimpor `./.figma/make/site.json`, tetapi direktori `.figma` tidak ada | Dev server, build, dan type-check tidak dapat memuat config | Blocker |
| Empat plugin custom Figma berada langsung di `vite.config.ts`: site configuration, error overlay replay, refresh fallback, dan Make Kit stories | Config sepanjang ±300 baris sulit dirawat dan mengikat project ke runtime preview Figma | Tinggi |
| `index.html` memakai slot `<!-- figma:* -->` untuk `lang`, title, dan script | Tanpa plugin Figma, metadata menjadi kosong/tidak valid | Tinggi |
| Port default `8443`, strict port, `FIGMA`, `FIGMA_PUBLIC_URL`, dan `EMIT_SOURCEMAPS` bersifat sandbox-specific | Setup lokal/CI tidak mengikuti konvensi Vite biasa | Sedang |
| Nama package masih `figma-make-app` | Metadata artifact dan observability tidak merepresentasikan produk | Rendah |
| Tidak ada `packageManager` di `package.json` | Corepack harus mencari versi pnpm dari jaringan; build kurang reproducible | Sedang |
| Tidak ada `.gitignore` dan `.env.example` | Risiko artefak/dependency/secrets ikut Git pada fase berikutnya | Tinggi |
| PDF walkthrough disimpan di dalam `src/` | Asset dokumentasi bercampur dengan source bundle | Rendah |

Rekomendasi fase portability: simpan config Vite minimal, metadata HTML statis atau env-based, pindahkan fitur preview Figma ke config opsional terpisah bila masih diperlukan, deklarasikan versi pnpm, dan tambah ignore/environment template. Itu harus menjadi perubahan kecil tersendiri sebelum integrasi API.

## Route dan halaman yang tersedia

Semua route didefinisikan di `src/App.tsx`. Tidak ada lazy loading, route-level error boundary, authenticated route guard, maupun permission guard.

| Route | Halaman | Peran di navigasi | Kondisi saat ini |
|---|---|---|---|
| `/` | Redirect default per role | Semua | Client-only |
| `/user/dashboard` | User Dashboard | Requester | KPI, daftar, quick action dummy |
| `/user/create-ticket` | Create Ticket | Requester | Wizard 3 langkah, submit simulasi |
| `/user/tickets` | Ticket History | Requester | Filter data statis |
| `/user/tickets/:id` | Ticket Detail | Secara praktik semua role | Detail global tanpa authorization; ID tidak valid jatuh ke tiket pertama |
| `/user/uat` | UAT | Requester | Satu tiket/test case hard-coded |
| `/supervisor/dashboard` | Supervisor Dashboard | Supervisor | Ringkasan dummy |
| `/supervisor/validation-queue` | Validation Queue | Supervisor | Validasi/revisi/tolak simulasi |
| `/itlead/dashboard` | IT Lead Dashboard | IT Lead | Ringkasan dummy |
| `/itlead/triage` | Triage Queue | IT Lead | Assign PIC/prioritas/SLA simulasi |
| `/itlead/priority` | Priority & SLA | IT Lead | Tampilan/read-only dummy |
| `/pic/dashboard` | PIC Dashboard | PIC | Ringkasan dummy |
| `/pic/workspace` | Workspace | PIC | Update progress/status simulasi |
| `/pic/rca` | Root Cause Analysis | PIC | Form simpan simulasi |
| `/pic/testing` | Internal Testing | PIC | Form submit simulasi |
| `/qa/dashboard` | QA Dashboard | QA | Queue dummy |
| `/qa/testing` | Testing Form | QA | Test result/defect simulasi |
| `/manager/approval` | Manager Approval | Manager | Approve/reject simulasi |
| `/sla-monitoring` | SLA Monitoring | IT Lead, Manager, Executive | Perhitungan statis, detail teknis global |
| `/notifications` | Notification Center | Semua selain Executive/Admin via menu | Read state lokal; prop role tidak dipakai |
| `/executive/dashboard` | Executive Dashboard | Executive | KPI/chart statis |
| `/executive/statistics` | Statistics & Analytics | Executive | Chart statis; type error Recharts |
| `/admin/console` | Admin Console | Admin | Link modul dan KPI dummy |
| `/admin/users` | User Management | Admin | Tambah user simulasi |
| `/admin/divisions` | Division & Application | Admin | Tambah master data simulasi |
| `/admin/sla-rules` | SLA Rules | Admin | Tambah/edit simulasi |
| `/admin/escalation` | Escalation Matrix | Admin | Tombol tambah tidak memiliki handler |
| `/admin/audit-log` | Audit Log | Admin | Filter data lokal |
| `*` | Redirect default per role | Semua | Menutupi 404 dan unauthorized |

## Inventaris komponen reusable

`Layout.tsx` menyediakan sidebar, role switcher demo, header, user identity, notification indicator, dan content shell. `ui.tsx` menyediakan `StatusBadge`, `PriorityBadge`, `CategoryBadge`, `SLAIndicator`, `Button`, `Input`, `Select`, `Textarea`, `Card`, `KPICard`, `Modal`, `Toast`, `Table`, `TR`, `TD`, `Avatar`, `ActivityTimeline`, `PageHeader`, `FilterBar`, `EmptyState`, `OverSLABanner`, `Tabs`, dan `SectionCard`.

Keterbatasan komponen penting:

- `Modal` belum memiliki focus trap, Escape handling, restore focus, `role="dialog"`, atau label ARIA.
- Card/row clickable memakai `div`/`tr`, sehingga tidak keyboard-accessible.
- Input label tidak terhubung melalui `htmlFor`/`id`; error tidak memakai `aria-describedby`.
- Toast tidak memiliki live region dan timer dibuat oleh masing-masing halaman.
- Table sudah `overflow-x-auto`, tetapi filter, action bar, form grid, sidebar, dan header sering memiliki lebar tetap.
- `Avatar` menerima prop `color` tetapi tidak menggunakannya.

## Data dummy dan sumber simulasi

`src/data.ts` adalah single source untuk 10 user, 8 tiket, 9 activity log, 5 notifikasi, 9 aplikasi, 10 divisi, 4 SLA rule, label/status/color, helper tanggal/SLA, KPI, dan semua chart series. Data tambahan hard-coded terdapat di:

- UAT test cases (`user/UAT.tsx`)
- QA test cases (`qa/TestingForm.tsx`)
- SLA custom rules (`admin/SLARules.tsx`)
- escalation matrix (`admin/EscalationMatrix.tsx`)
- audit records (`admin/AuditLog.tsx`)
- demo accounts dan password (`Login.tsx`)
- beberapa ringkasan/array aksi di dashboard.

SLA dummy bertentangan dengan aturan proyek. UI memakai 24/48/168/336 jam kalender, sedangkan target adalah Critical 4 jam kerja, High 8 jam kerja, Medium 2 hari kerja, Low 5 hari kerja. `over_sla` juga dipakai sebagai status tiket sekaligus boolean, padahal keterlambatan adalah kondisi orthogonal terhadap workflow.

## Tombol, modal, dan action

| Area | Action/modal | Implementasi aktual |
|---|---|---|
| Login | Login form dan 8 quick-login account | Timer lalu mengubah state `loggedIn`; email/password tidak diverifikasi |
| Layout | Ganti role | Mengubah role client dan pindah route; merupakan impersonation tanpa auth |
| Create Ticket | Wizard, attachment picker, submit | Hanya state lokal/toast/navigate; file tidak diunggah |
| Ticket Detail | Tambah komentar, download attachment, mulai UAT | Komentar tidak disimpan; download tidak memiliki URL/action; UAT tidak membawa ticket ID |
| UAT | Pass/fail/skip, approve/reject modal | Hasil hilang setelah navigasi; status tiket tidak berubah |
| Validation | Validate, request revision, reject modal | Toast saja; queue/data tidak berubah |
| Triage | Set priority, PIC, SLA, note dan confirm assign | Toast saja; nilai tidak divalidasi/dipersist |
| PIC Workspace | Update progress, change status modal | Toast saja; timeline/status tidak berubah |
| RCA | Save form | Toast saja |
| Internal Testing | Submit result | Toast dan navigate; tidak membuat test run |
| QA Testing | Test cases, defect, recommendation, submit | State lokal dan navigate; tidak membuat defect/status transition |
| Manager Approval | Approve/reject modal | Toast saja; reject comment tidak diwajibkan |
| Notifications | Mark read/all read | `Set` lokal; notifikasi sumber tidak berubah |
| Admin User | Add user modal | Toast; daftar tidak bertambah |
| Admin Division/App | Add modals | Toast; master data tidak bertambah |
| Admin SLA | Add/edit modal | Toast; edit tidak mengisi form record dan data tidak berubah |
| Escalation | Add rule | Tombol inert tanpa `onClick` |
| Audit | Search/filter | Client-side atas array hard-coded |
| Dashboard/statistics | Filter/chart/navigation | Data statis; beberapa link menuju route requester untuk semua role |

Tidak ada delete/deactivate user, edit master data yang nyata, ticket cancel/reopen/transfer, watchers, knowledge base, deployment action, monitoring outcome, close action, notification delivery, atau audit capture.

## Bug dan gap teknis

### Build dan TypeScript

- `corepack pnpm build`: **gagal**, unresolved import `.figma/make/site.json` dari `vite.config.ts`.
- `corepack pnpm exec tsc --noEmit`: **gagal**, 27 diagnostic: missing Figma JSON, satu mismatch callback label `Pie` Recharts, serta 25 unused import/parameter/local diagnostic.
- `corepack pnpm format --check .`: **gagal**, 35 dari 36 file dilaporkan tidak sesuai formatter.
- Tidak ada script `typecheck`, `test`, `lint`, atau test file.
- Dependency memakai rentang caret dan lockfile, tetapi tidak ada `packageManager`/engine policy. Node audit environment adalah v24.18.0.

### Authentication dan authorization

- Authentication hanya boolean memory state; refresh mengembalikan ke login.
- Password demo plaintext ditampilkan dan tidak divalidasi.
- Semua route dapat dibuka oleh role mana pun setelah login; sidebar bukan security boundary.
- Executive dapat mencapai detail teknis tiket melalui route shared/navigation dan tidak ada field-level redaction.
- Tidak ada CSRF/Sanctum flow, session restore, logout, forgot password, account lock, MFA/SSO decision, policy, atau audit auth.

### Routing

- `BrowserRouter` membutuhkan server fallback untuk deep link; belum ada deployment config.
- Param detail invalid menampilkan `TICKETS[0]`, bukan 404.
- UAT, QA testing, RCA, internal testing, priority, dan workspace tidak memakai ticket ID di URL; deep link dan multi-ticket selection ambigu.
- Fallback selalu redirect, sehingga not-found dan forbidden tidak dapat dibedakan.
- Cross-role links menuju `/user/tickets/:id`, memperlihatkan coupling route dan kemungkinan data leakage.

### State/data

- Semua mutation hanya local component state, toast, dan timer.
- Tidak ada server-state/cache layer, API client, consistent error/loading/empty handling, optimistic update policy, pagination, atau URL-backed filters.
- Role/user aktif diturunkan dari user pertama dengan role tersebut; bukan identity session.
- Status type tidak mencerminkan workflow target: `analysis`, `planning`, `development`, `qa_testing`, `approval`, `ready_to_deploy`, `deployed`, `monitoring`, `need_revision`, `reopened`, `cancelled`, `transferred`, `waiting_user`, dan `waiting_external_party` tidak dimodelkan dengan benar.
- `in_progress`, `pending_approval`, `approved`, `deploying`, dan `done` adalah padanan ambigu yang akan menyulitkan migrasi.
- Waktu memakai string UTC dummy dan jam kalender; belum ada kalender kerja, holiday, pause/resume SLA, atau timezone policy.

### Responsiveness dan accessibility

Audit source menunjukkan layout desktop-first. Sidebar selalu mengambil 60/16 rem-equivalent width dan tidak berubah menjadi drawer/overlay di mobile. Padding utama `p-6`, header fixed content, button groups, filter widths (`w-36` sampai `w-56`), banyak `grid-cols-2` tanpa breakpoint, serta form/action flex tanpa wrap berpotensi overflow pada 320–390 px. Tabel bisa horizontal scroll, tetapi pengguna harus menggeser kolom penting.

Responsive visual belum dapat diverifikasi karena dev/build gagal pada config Figma. Tidak ada automated accessibility test. Selain masalah modal dan clickable non-button, icon banyak berupa emoji/mojibake pada output PowerShell, sehingga encoding repository/editor/deployment harus divalidasi sebagai UTF-8.

## Halaman workflow yang belum ada

| Kebutuhan | Status UI |
|---|---|
| Ticket analysis dan work log terstruktur | Hanya textarea progress generik |
| Planning: scope, estimate, risk, dependencies, target release | Belum ada |
| Development lifecycle/checklist/link change | Belum ada |
| QA defect management dan retest history | Form satu kali; belum ada lifecycle defect |
| UAT assignment/scheduling/history per ticket | Form hard-coded tanpa route ID |
| Approval history/delegation/multi-level approval | Belum ada |
| Deployment plan, checklist, window, rollback, evidence | Belum ada |
| Post-deployment monitoring dan outcome | Belum ada |
| Closing summary/resolution code/requester confirmation | Belum ada |
| Revision/reject/reopen/cancel/transfer/waiting flows | Belum ada halaman/action konsisten |
| SLA breach/escalation event detail | Hanya tampilan dummy |
| Notification preferences/templates/delivery status | Belum ada |
| Knowledge Base list/detail/search/editor/link-to-ticket | Belum ada seluruhnya |
| Profile/account/logout | Belum ada |
| 403/404/error/offline state | Belum ada |

## Kesimpulan current state

Prototype memiliki cakupan visual yang luas dan komponen reusable yang cukup untuk dipertahankan. Namun, ia belum merupakan aplikasi fungsional: build tidak portable, type-check gagal, identity/RBAC tidak aman, model status dan SLA berbeda dari business rule, semua data/mutasi bersifat statis, dan workflow setelah approval belum terwakili. Jalur yang aman adalah menstabilkan frontend lebih dahulu, menyepakati state machine dan permission contract, lalu mengganti satu vertical slice pada satu waktu dengan API.
