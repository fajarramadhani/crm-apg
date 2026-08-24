# AGENTS.md — APG CRM (Tic Hub)

Panduan untuk agen AI dan developer yang bekerja pada repository ini.

## Gambaran Proyek

Monorepo full-stack CRM & IT Service Management internal APG:

| Bagian | Path | Stack |
| --- | --- | --- |
| Frontend | `frontend/` | React 19, TypeScript, Vite 8, Tailwind CSS v4, React Router 7 |
| Backend | `backend/` | Laravel 11, Sanctum (SPA cookie auth), MySQL 8 |
| Dokumentasi | `docs/` | Laporan implementasi, arsitektur, runbook |

## Struktur Kunci

```text
apg-crm/
├── frontend/
│   ├── src/
│   │   ├── api/            # HTTP client (apiClient) dan kontrak API
│   │   ├── components/     # Komponen UI reusable (+ knowledgeBase/, notifications/, pic/, supervisor/)
│   │   ├── config/         # Konfigurasi runtime
│   │   ├── context/        # React context (AuthContext, LoadingContext)
│   │   ├── hooks/          # Custom hooks
│   │   ├── lib/            # Utilitas library
│   │   ├── pages/          # Halaman, dikelompokkan per role (admin/, pic/, qa/, supervisorIt/, dll.)
│   │   ├── repositories/   # Lapisan akses API per domain
│   │   ├── services/       # Layanan aplikasi frontend
│   │   └── types.ts        # Tipe TypeScript global
│   └── vite.config.ts      # Proxy /api dan /sanctum ke backend
├── backend/
│   ├── app/
│   │   ├── Console/Commands/   # Command artisan kustom
│   │   ├── Http/{Controllers,Requests,Resources}/Api/V1/
│   │   ├── Models/         # Eloquent models (fillable via atribut #[Fillable] di atas class)
│   │   ├── Policies/       # Otorisasi resource
│   │   ├── Services/       # Business logic (workflow, SLA, reports, WhatsApp)
│   │   └── Support/        # Helper (ApiResponse, AuditLogger)
│   ├── database/migrations/
│   ├── routes/api.php      # Semua route API /api/v1
│   └── tests/Feature/      # PHPUnit (SQLite in-memory)
└── docs/                   # WAJIB: laporan implementasi ditulis di sini
```

## Perintah

Frontend (gunakan **npm**, bukan pnpm):

```bash
cd frontend
npm ci                    # install dari package-lock.json
npm run typecheck         # tsc --noEmit
npm run format:check      # prettier --check
npm run build             # typecheck + vite build
```

Backend:

```bash
cd backend
php artisan test                      # PHPUnit, SQLite :memory:
vendor/bin/pint --test                # code style
php artisan migrate:fresh --seed      # HANYA development, database disposable
```

## Konvensi Penting

1. **Laravel adalah authority** untuk identity, otorisasi, transisi workflow, SLA, dan audit. Frontend tidak boleh menentukan permission sendiri — hanya merender `permissions` dan `allowed_actions` dari backend.
2. **Transisi workflow harus eksplisit**: row locking (`lockForUpdate`), transaksi DB, audit trail, dan optimistic concurrency via kolom `version`.
3. **Model Eloquent memakai atribut PHP** `#[Fillable([...])]` yang dipasang **di atas deklarasi class** (lihat `app/Models/User.php`, `app/Models/AuditLog.php`).
4. **Testing backend memakai SQLite in-memory** (`phpunit.xml`). Jangan mengubah konfigurasi DB test; job CI terpisah (`backend-mysql`) memvalidasi migrasi terhadap MySQL 8.4.
5. **Waktu disimpan UTC**, ditampilkan `Asia/Jakarta`.
6. **Tailwind CSS v4** dimuat via plugin Vite — tanpa PostCSS config. Gunakan utility class langsung di JSX.
7. **API response** selalu melewati `App\Support\ApiResponse` (envelope `success/message/data/meta`).
8. **Keamanan**: jangan pernah commit credential, password, atau token. Password suplai lewat environment variable saat eksekusi command.

## ⚠️ Konvensi Wajib: Dokumentasi

**Setiap kali sebuah pekerjaan/fitur/perbaikan diselesaikan, WAJIB dibuatkan laporan di folder `docs/`** mengikuti gaya laporan tahap yang ada (contoh: `docs/crm-production-identity-bootstrap-implementation-report.md`). Laporan minimal berisi:

1. Judul, tanggal, branch, status akhir
2. Ringkasan apa yang dikerjakan
3. Keputusan desain penting beserta alasannya
4. Daftar file yang berubah
5. Hasil verifikasi (test, lint, typecheck)

Selain itu, bila pekerjaan tersebut memengaruhi risiko produksi, perbarui baris terkait di `docs/known-issues-and-risks.md`. Risiko tidak boleh ditandai *closed* tanpa bukti staging atau validasi manusia.

## Development Server

```bash
cd backend && php artisan serve    # http://localhost:8000
cd frontend && npm run dev         # http://localhost:5173
```

Health check: `GET http://localhost:8000/api/v1/health`

