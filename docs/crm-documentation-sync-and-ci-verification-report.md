# Laporan — Sinkronisasi Dokumentasi dan Verifikasi CI MySQL

Tanggal implementasi: 24 Agustus 2026
Project: APG CRM (Tic Hub)
Branch: `development`
Status akhir: **READY FOR REVIEW**

## 1. Ringkasan

Dua pekerjaan diselesaikan pada sesi ini:

1. **Sinkronisasi dokumentasi (prioritas #4)** — menghapus inkonsistensi antara dokumentasi dan kondisi repository aktual.
2. **Verifikasi prioritas #2 (CI MySQL)** — membuktikan bahwa job `backend-mysql` GitHub Actions telah menghasilkan green run, sehingga risk register dapat diperbarui dengan bukti.

## 2. Temuan Verifikasi CI

Melalui `gh run list` / `gh run view` diverifikasi bahwa CI run **31564763157** (push ke `development`, 2026-08-12) menghasilkan:

| Job | Hasil |
| --- | --- |
| `backend` | ✓ pass (52s) |
| `backend-mysql` (MySQL 8.4 service container, siklus migrate/rollback/seed + full suite) | ✓ pass (2m5s) |
| `frontend` (npm ci, typecheck, format:check, build) | ✓ pass (32s) |

Klaim lama pada risk register ("has not yet produced a green workflow result") sudah tidak akurat.

**Keterbatasan**: migration yang ditambahkan setelah run tersebut (production identity bootstrap) belum tervalidasi job MySQL — akan tervalidasi otomatis pada push berikutnya. Verifikasi lokal terhadap MySQL juga tidak memungkinkan karena service MySQL lokal tidak berjalan (connection refused pada port 3306).

## 3. Perubahan Dokumentasi

### AGENTS.md (ditulis ulang seluruhnya)

- Menghapus deskripsi usang "Figma Make app" yang tidak sesuai struktur monorepo.
- Menuliskan struktur aktual: `frontend/src/{api,components,config,context,hooks,lib,pages,repositories,services}`, backend layer lengkap, dan konvensi kunci proyek (Laravel sebagai authority, atribut `#[Fillable]` di atas class, SQLite in-memory testing, envelope ApiResponse).
- **Menambahkan konvensi wajib**: setiap pekerjaan yang selesai harus dibuatkan laporan di `docs/`, dan risk register wajib diperbarui bila risiko produksi terdampak.

### README.md

- Package manager: semua referensi `corepack pnpm` diganti `npm` (`npm ci`, `npm run dev`, `npm run typecheck`, dst.) — konsisten dengan migrasi pnpm→npm pada commit `b8e32ae`.
- Persyaratan: "Corepack dan pnpm 11" → "npm 10+".
- Menghapus klaim dependency **TanStack Query** yang tidak ada di `frontend/package.json`.
- Struktur folder frontend disesuaikan folder aktual (`api/`, `context/`, `repositories/`; bukan `app/`, `types/`, `utils/`).
- Jumlah migration dikoreksi dari "22" menjadi **49** (jumlah file aktual di `backend/database/migrations`), plus catatan tahap lanjutan (dynamic workflow, public access, WhatsApp/Fonnte, identity bootstrap).

### docs/known-issues-and-risks.md

- Baris **Data**: status "Verification pending" → "**Partially verified**", likelihood Medium → Low, dengan referensi eksplisit run CI hijau; sisa risiko staging compatibility tetap dicatat.
- (Sebelumnya pada sesi yang sama) baris **Identity**: diperbarui menjadi "Mitigated — staging evidence pending".

## 4. File yang Berubah

- `AGENTS.md`
- `README.md`
- `docs/known-issues-and-risks.md`
- `docs/crm-documentation-sync-and-ci-verification-report.md` (laporan ini)

Tidak ada perubahan kode aplikasi pada sesi ini.

## 5. Verifikasi

- Pencarian ulang `pnpm|TanStack|corepack` di README.md: **0 hasil**.
- Jumlah migration diverifikasi via `Get-ChildItem`: 49 file.
- Status CI diverifikasi via GitHub CLI (`gh run view 31564763157`).

## 6. Sisa Pekerjaan

- Push perubahan ini agar job `backend-mysql` memvalidasi 2 migration baru (identity bootstrap).
- Verifikasi kompatibilitas skema terhadap topologi MySQL staging (tetap terbuka di risk register).
- Prioritas #3 (audit N+1 + agregasi SQL) dan #5 (PHPStan/Larastan + unit test frontend) masih tertunda sesuai rencana.
