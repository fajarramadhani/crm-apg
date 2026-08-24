# Laporan Implementasi — Production Identity Bootstrap

Tanggal implementasi: 24 Agustus 2026
Project: APG CRM (Tic Hub)
Branch: `development`
Status akhir: **READY FOR REVIEW**

## 1. Ringkasan

Gap identitas produksi pada risk register (`docs/known-issues-and-risks.md`, kategori Identity) telah ditutup sebagian dengan mengimplementasikan:

1. **Command bootstrap super admin produksi** (`php artisan users:bootstrap-production`) — single-use, hanya berjalan di environment `production`, dan menolak eksekusi ketika sudah ada satu pun user (termasuk soft-deleted).
2. **Rotasi password wajib login pertama** melalui flag `must_change_password` dan endpoint `POST /api/v1/auth/change-password`.
3. **Tabel `audit_logs` append-only** beserta model `AuditLog` dan helper `App\Support\AuditLogger` — mendesain ulang tabel yang sebelumnya hanya ada di `docs/database-design.md` tanpa implementasi.
4. **Layar "Ganti Password" frontend** yang mengunci seluruh route workspace sampai rotasi password selesai.

SSO dan alur undangan (invitation) tetap di luar scope increment ini dan masih tercatat pada risk register.

## 2. Keputusan Desain

- Command tidak menerima password sebagai opsi CLI (terlihat di shell history). Password disuplai via env `TIC_HUB_ADMIN_PASSWORD`; bila kosong, command meng-generate one-time password (`Str::password(16)`) dan mencetaknya tepat satu kali.
- Guard environment memakai `config('app.env') === 'production'` agar dapat diuji pada PHPUnit (SQLite in-memory), berbeda dari `ProvisionTestingUserCommand` yang memakai `app()->environment()`.
- Regenerasi session ID setelah ganti password bersifat kondisional (`$request->hasSession()`) karena store session hanya ada pada request stateful.
- Event audit: `identity.bootstrap.completed`, `identity.bootstrap.refused`, `identity.password_changed`.

## 3. File yang Berubah

### Migration dan Persistence

- `backend/database/migrations/2026_08_24_000001_add_must_change_password_to_users_table.php`
- `backend/database/migrations/2026_08_24_000002_create_audit_logs_table.php`
- `backend/app/Models/AuditLog.php`
- `backend/app/Models/User.php` (fillable + cast `must_change_password`)

### Backend Logic

- `backend/app/Console/Commands/BootstrapProductionUserCommand.php` (baru)
- `backend/app/Support/AuditLogger.php` (baru)
- `backend/app/Http/Requests/Api/V1/ChangePasswordRequest.php` (baru)
- `backend/app/Http/Controllers/Api/V1/AuthController.php` (method `changePassword`)
- `backend/app/Http/Resources/Api/V1/AuthenticatedUserResource.php` (field baru)
- `backend/routes/api.php` (route change-password)

### Frontend

- `frontend/src/pages/settings/ChangePassword.tsx` (baru)
- `frontend/src/App.tsx` (route `/change-password` + redirect paksa)
- `frontend/src/context/AuthContext.tsx` (method `refreshUser`)
- `frontend/src/repositories/authRepository.ts`, `frontend/src/services/authService.ts`
- `frontend/src/types.ts` (`must_change_password?: boolean`)

### Dokumentasi dan Konfigurasi

- `README.md` (section "Bootstrap Super Admin Production")
- `backend/.env.example` (`# TIC_HUB_ADMIN_PASSWORD=`)

## 4. Verifikasi

| Pemeriksaan | Hasil |
| --- | --- |
| `php artisan test --filter="ProductionIdentityBootstrapTest\|ChangePasswordTest"` | 10 passed, 34 assertions |
| Full suite backend (`php artisan test`) | 503 passed, 3234 assertions |
| Frontend typecheck (`tsc --noEmit`) | Pass |
| Prettier check file frontend terdampak | Pass |
| Pint file backend baru/terdampak | Pass |

Cakupan test baru:

- Command menolak environment non-production, role superadmin hilang, dan password env di bawah 12 karakter.
- Command single-use: ditolak saat sudah ada user, percobaan tercatat sebagai `identity.bootstrap.refused`.
- Bootstrap sukses membuat superadmin aktif dengan `must_change_password=true` + event audit `identity.bootstrap.completed`.
- Endpoint change-password: 401 tanpa sesi, penolakan password lama salah / password baru pendek / sama dengan lama, sukses mengganti hash, membersihkan flag, dan menulis audit `identity.password_changed`.
- `/auth/me` mengekspos flag `must_change_password`.

## 5. Langkah Operasional Saat Go-Live

```bash
php artisan db:seed --class=RoleSeeder
php artisan users:bootstrap-production --name="Nama Admin" --email="admin@apg.co.id"
```

Password opsional via `TIC_HUB_ADMIN_PASSWORD`. Admin wajib mengganti password pada login pertama melalui layar "Ganti Password".

## 6. Sisa Pekerjaan

- Validasi bootstrap drill di staging (syarat menandai risk register selesai).
- Keputusan arsitektur SSO atau alur invitation untuk lifecycle berkelanjutan.
- Integrasi `audit_logs` ke layar admin (saat ini data tercatat, belum ada UI pembacaan).
