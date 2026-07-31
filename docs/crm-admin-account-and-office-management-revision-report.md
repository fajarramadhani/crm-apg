# CRM Admin Account and Office Management Revision Report

## 1. Branch dan Commit Awal

- Branch: `feature/crm-simplified-dynamic-workflow`
- Commit awal: `54274062dcd69bfec11556bc3430317215c37667`
- `git fetch origin` selesai tanpa error.
- Tidak ada perpindahan branch, merge, commit, push, force push, atau deploy.

## 2. Kondisi Working Tree

Working tree sudah memiliki perubahan account management sebelum revisi ini, termasuk konfigurasi permission, role seeder, route Admin, controller/request/resource user, test, route frontend, navigation, service user, dan `UserManagement.tsx`. Seluruh perubahan tersebut dipertahankan dan diintegrasikan. Tidak dilakukan reset, clean, stash, atau revert.

Artefak existing yang tidak diubah atau dihapus meliputi `backend/package-lock.json`, dokumen Stage 13, dan evidence Stage 13.

## 3. Analisis Fitur Sebelumnya

- User existing memiliki relasi `role`, `division`, dan legacy `branch`, tetapi belum memiliki nomor telepon atau relasi Office.
- Legacy `branches` tetap dipertahankan karena tidak memiliki konsep `office_type` dan digunakan oleh fitur CRM existing.
- Role canonical tersedia dengan key `requester`, `supervisor_it`, `pic_it_develop`, dan `pic_it_support` serta role legacy lain.
- Account management existing menyediakan list, filter, edit, aktivasi/nonaktivasi, dan opsi workflow.
- Permission menggunakan registry konfigurasi. `users.manage` dan `master_data.manage` hanya dimiliki Admin.
- API menggunakan envelope `ApiResponse` dengan `success`, `message`, `data`/`error`/`errors`, dan `meta.request_id`.

## 4. Migration Offices

Migration additive `2026_07_29_000001_create_offices_table.php` membuat:

- `id`
- `name`
- `office_type` enum `pusat|cabang`
- timestamps
- unique composite `name, office_type`
- index `office_type`

Tidak ada tabel lama yang diubah atau dihapus.

## 5. Relasi Users dan Offices

Migration additive `2026_07_29_000002_add_office_and_phone_to_users_table.php` menambahkan:

- `users.office_id`, nullable, indexed melalui foreign key, mengacu ke `offices.id`, dan `nullOnDelete()`.
- `users.phone`, nullable dengan panjang 30 untuk kompatibilitas user legacy.

Model `User` menambahkan fillable yang dibutuhkan dan relasi `belongsTo Office`. Nilai user legacy, role, `branch_id`, dan `division_id` tidak dimigrasikan atau diubah.

## 6. Model Office

`App\Models\Office` menyediakan fillable terbatas `name` dan `office_type`, relasi `hasMany User`, serta scope `pusat()` dan `cabang()`.

## 7. Seeder Office

`OfficeSeeder` menggunakan `firstOrCreate` berdasarkan kombinasi nama dan tipe. Seeder terdaftar setelah `RoleSeeder` pada `DatabaseSeeder` dan dapat dijalankan berulang kali tanpa update atau delete.

Seeder tidak mengubah user, role user, workflow, atau office yang dikelola Admin.

## 8. Data Awal

- Kantor Pusat, `pusat`
- Bandung, `cabang`
- Lampung, `cabang`
- Makassar, `cabang`

Seeder production tidak dijalankan selama pekerjaan ini.

## 9. Management Cabang

Halaman `/admin/offices` dan menu `Management Cabang` ditambahkan untuk Admin. Halaman menyediakan daftar nama, tipe, tanggal dibuat, tambah, edit, hapus, loading state, empty state, feedback API, serta filter Semua/Kantor Pusat/Kantor Cabang.

Form hanya mengirim `name` dan `office_type`. Penghapusan office yang digunakan user ditolak. Penghapusan atau perubahan tipe satu-satunya Kantor Pusat juga ditolak.

## 10. Office API

Endpoint authenticated dan Admin-only:

- `GET /api/v1/admin/offices`
- `POST /api/v1/admin/offices`
- `GET /api/v1/admin/offices/{office}`
- `PUT /api/v1/admin/offices/{office}`
- `DELETE /api/v1/admin/offices/{office}`

Route menggunakan middleware `auth:sanctum`, `active`, `permission:master_data.manage`, dan limiter Admin. Route model binding menyembunyikan resource tidak valid dengan 404. Write menggunakan transaction dan lock untuk invariant Kantor Pusat.

## 11. Tab Buat Akun Requester

Modal Buat Akun default ke tab `Buat Akun Requester`. Field:

- Mode Kantor Pusat/Kantor Cabang
- Pilih Cabang hanya saat mode cabang
- Nama
- Email
- No. Telp
- Password
- Konfirmasi Password

Daftar cabang berasal dari `offices` API options dan difilter `office_type=cabang`, sehingga office baru muncul tanpa perubahan frontend. Backend otomatis memilih office pusat pertama untuk mode pusat dan menentukan role `requester`.

## 12. Tab Buat Akun IT

Field terdiri dari Role, Nama, Email, No. Telp, Password, dan Konfirmasi Password. UI hanya menampilkan:

- Supervisor ke `supervisor_it`
- IT Developer ke `pic_it_develop`
- IT Support ke `pic_it_support`

Office tidak ditampilkan dan backend menyimpan `office_id=null`.

## 13. Mapping Role

Pembuatan akun dipisah ke endpoint Requester dan IT. Endpoint POST generik `/admin/users` dihapus agar Admin tidak dapat membuat Admin atau role legacy melalui payload role bebas. Backend mencari role berdasarkan key, bukan ID statis.

List dan edit account management sebelumnya tetap tersedia agar fungsi CRM sebelumnya dipertahankan.

## 14. Password Visibility

Password dan konfirmasi memiliki state visibility terpisah, default tersembunyi, tombol keyboard-accessible, dan label aksesibel tampil/sembunyi yang sesuai. Password dibersihkan setelah sukses dan ketika tab berganti. Password tidak disimpan ke local storage.

## 15. Validation

- Nama, email, phone, password, dan confirmation wajib pada pembuatan akun.
- Nama, email, dan phone di-trim; email dinormalisasi lowercase.
- Email format dan uniqueness divalidasi.
- Phone menerima karakter nomor telepon yang aman dengan panjang 7-30.
- Password mengikuti kebijakan existing minimal 12 dan confirmed.
- Mode office dan tipe office menggunakan whitelist.
- Office ID wajib dan harus cabang untuk mode cabang.
- Office mode pusat menolak ID cabang dan memilih office pusat backend.
- Kombinasi nama dan office type unik.
- Request hanya membaca field tervalidasi; field tambahan sensitif tidak digunakan.

## 16. Authorization

- Account endpoints memerlukan `users.manage`, yang hanya diberikan kepada Admin.
- Office endpoints memerlukan `master_data.manage`, yang hanya diberikan kepada Admin.
- Test membuktikan unauthenticated mendapat 401 dan non-Admin/Requester mendapat 403.
- Route model binding memberikan 404 untuk office ID tidak tersedia.

## 17. Security

- Role Requester ditentukan backend.
- Role IT dibatasi whitelist tiga key final.
- Endpoint tidak dapat membuat Admin, Requester melalui tab IT, QA, atau role legacy.
- Password di-hash dan tidak ada pada resource API.
- Office tidak menggunakan cascade delete ke user.
- Delete office yang digunakan user menghasilkan 409 `OFFICE_IN_USE`.
- Invariant minimal satu office pusat dilindungi pada update dan delete dengan transaction dan row lock.
- Exception API tetap menggunakan handler existing tanpa stack trace pada response.

## 18. Responsive

UI menggunakan modal dengan batas tinggi viewport dan scroll, grid yang berubah satu/dua kolom, tab dua kolom dengan font mobile, table wrapper horizontal existing, serta komponen input/select full width. Build CSS berhasil.

Verifikasi visual manual pada viewport 1440x900, 1366x768, 768x1024, 390x844, dan 360x800 belum dapat diotomasi karena project tidak memiliki browser/E2E test runner pada environment ini.

## 19. Accessibility

- Tab memakai `tablist`, `tab`, `tabpanel`, `aria-selected`, `aria-controls`, roving `tabIndex`, dan Arrow Left/Right.
- Input/select memakai label terhubung, `aria-invalid`, dan deskripsi error dari shared component.
- Password controls adalah button native dengan accessible labels terpisah.
- Loading dan error menggunakan status/alert semantics.
- Focus style global existing tetap digunakan.
- Status akun tetap memiliki teks, tidak hanya warna.

## 20. Backend Tests

Test yang ditambahkan/diperbarui:

- `OfficeSeederTest`
- `OfficeManagementTest`
- `AdminAccountCreationTest`
- `AdminUserManagementTest`
- `DatabaseSeederSafetyTest`

Hasil final `php artisan test`: 359 passed, 1,903 assertions.

Hasil final `vendor/bin/pint --test`: PASS.

## 21. Frontend Tests

- `npm run typecheck`: PASS.
- `npm run build`: PASS, 1,870 modules transformed.
- Project belum memiliki component/E2E test runner, sehingga interaksi browser dan viewport dicatat sebagai pending manual verification.

## 22. Legacy Regression

- Dynamic Workflow Engine tidak diubah.
- Assignment engine tidak diubah.
- Ticket intake, Supervisor Control Center, PIC Workspace, QA/UAT legacy, ticket status, dan workflow snapshots tidak diubah.
- Semua 359 backend tests lulus.
- `crm:role-mapping --file=../docs/crm-role-mapping-dry-run.csv --dry-run`: PASS, 0 proposed changes, tidak ada database update.
- `crm:workflow-check` dan `crm:workflow-check --all`: FAIL karena database lokal tidak memiliki workflow `config_status=active` dan feature flag `CRM_DYNAMIC_WORKFLOW_ENABLED=false`. Kondisi ini tidak diaktifkan atau diperbaiki karena requirement melarang mengaktifkan workflow.

## 23. File yang Berubah

File revisi utama:

- `backend/database/migrations/2026_07_29_000001_create_offices_table.php`
- `backend/database/migrations/2026_07_29_000002_add_office_and_phone_to_users_table.php`
- `backend/app/Models/Office.php`
- `backend/app/Models/User.php`
- `backend/database/seeders/OfficeSeeder.php`
- `backend/database/seeders/DatabaseSeeder.php`
- `backend/app/Http/Controllers/Api/V1/AdminOfficeController.php`
- `backend/app/Http/Controllers/Api/V1/AdminUserController.php`
- `backend/app/Http/Requests/Api/V1/StoreOfficeRequest.php`
- `backend/app/Http/Requests/Api/V1/UpdateOfficeRequest.php`
- `backend/app/Http/Requests/Api/V1/StoreRequesterAccountRequest.php`
- `backend/app/Http/Requests/Api/V1/StoreItAccountRequest.php`
- `backend/app/Http/Resources/Api/V1/OfficeResource.php`
- `backend/app/Http/Resources/Api/V1/AdminUserResource.php`
- `backend/routes/api.php`
- `backend/tests/Feature/OfficeSeederTest.php`
- `backend/tests/Feature/OfficeManagementTest.php`
- `backend/tests/Feature/AdminAccountCreationTest.php`
- `backend/tests/Feature/AdminUserManagementTest.php`
- `backend/tests/Feature/DatabaseSeederSafetyTest.php`
- `frontend/src/services/officeService.ts`
- `frontend/src/services/userService.ts`
- `frontend/src/pages/admin/OfficeManagement.tsx`
- `frontend/src/pages/admin/UserManagement.tsx`
- `frontend/src/App.tsx`
- `frontend/src/components/Layout.tsx`

Daftar Git juga tetap mencakup perubahan account management dan dokumentasi Stage 13 yang sudah ada sebelum revisi.

## 24. Error yang Ditemukan

- Test awal mengirim ID cabang dalam mode pusat; dikoreksi karena backend wajib menolaknya.
- Test authorization awal masih memakai state `actingAs` dari assertion sebelumnya; urutan test diperbaiki.
- SQLite tidak mendukung `COUNT(DISTINCT name, office_type)` dengan sintaks MySQL; assertion dibuat database-agnostic.
- Pint menemukan urutan import pada dua file; diperbaiki menggunakan Pint.
- Audit akhir menemukan satu-satunya Kantor Pusat dapat kehilangan tipe pusat melalui update; ditutup dengan transaction/lock dan regression test.
- Workflow health check lokal gagal karena tidak ada workflow aktif, sesuai kondisi environment existing.

## 25. Pending

- Jalankan migration dan `OfficeSeeder` pada environment target yang diotorisasi. Migration lokal saat pemeriksaan masih berstatus Pending dan tidak dijalankan otomatis.
- Verifikasi visual/interaksi manual untuk lima viewport acceptance.
- Workflow health check memerlukan workflow aktif pada environment yang memang ditujukan untuk workflow aktif. Workflow tidak diaktifkan selama revisi ini.

## 26. Risiko

- Unique office mengikuti collation database; sensitivitas huruf besar/kecil mengikuti konfigurasi MySQL environment.
- `phone` nullable untuk kompatibilitas legacy, tetapi wajib untuk akun baru melalui endpoint revisi.
- Existing legacy `branches` dan Office adalah dua domain terpisah. Ini disengaja untuk tidak merusak referensi `branch_id` lama.
- Database lokal belum menjalankan migration additive; UI/API memerlukan migration dan seed pada environment runtime yang sah.
- Verifikasi frontend saat ini mencakup TypeScript dan production build, belum browser automation.

## 27. Status Akhir

**PASS WITH PENDING**

Fitur, validation, authorization, test backend, Pint, frontend typecheck, dan frontend build lulus. Pending terbatas pada migration/seed environment target yang memerlukan izin, health check workflow aktif yang tidak boleh diaktifkan oleh revisi ini, dan verifikasi visual manual viewport.
