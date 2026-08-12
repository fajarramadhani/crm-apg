# CRM Requester Ticket Field and Handling Info Revision Report

## 1. Branch dan Commit Awal

- Branch: `feature/crm-simplified-dynamic-workflow`
- Commit awal: `54274062dcd69bfec11556bc3430317215c37667`
- `git fetch origin` berhasil.
- Tidak ada perpindahan branch, commit, push, merge, atau deploy.

## 2. Kondisi Working Tree

Working tree telah berisi revisi Admin Account, Office, Super Admin, serta kompatibilitas tiket Requester berbasis Office. Seluruh perubahan dipertahankan tanpa reset, clean, stash, restore, atau revert.

## 3. Requirement Lama

Requirement Stage 4 sebelumnya membatasi form Requester menjadi lima field dan melarang pemilihan kategori, sistem, serta urgency. Dokumen Stage 4 dipertahankan sebagai histori implementasi pada saat itu.

## 4. Requirement Baru

Untuk tiket baru, Requester wajib memilih kategori pengajuan, sistem aktif, judul, deskripsi, lampiran, dan Status Urgent. Link Submission wajib hanya untuk Error / Bug. Tiket lama tidak dibackfill.

## 5. Kategori Pengajuan

Ditambahkan `RequesterCategory` dengan value stabil:

- `request` menjadi Request
- `error_bug` menjadi Error / Bug
- `other` menjadi Lainnya

Kategori Requester disimpan terpisah dari `ticket_category_id` teknis agar Supervisor tetap dapat mengoreksi klasifikasi tanpa mengubah input awal Requester.

## 6. Master Sistem

Master existing `applications` digunakan. Dropdown mengambil `/api/v1/master/applications` sehingga sistem aktif baru dari Admin muncul otomatis.

`ApplicationSystemSeeder` menambahkan secara idempotent berdasarkan code:

- BPR CORE
- DWP CORE
- BROKER CARAKA
- FINANCE
- HRIS
- PRADA CORE
- COMPANY PROFILE

Seeder memakai `firstOrCreate`, tidak menghapus sistem lain dan tidak menimpa nama yang sudah diedit Admin.

## 7. Conditional URL Validation

`affected_url` existing digunakan. Untuk `error_bug`, URL wajib. Untuk `request` dan `other`, URL opsional. Jika diisi, seluruh kategori hanya menerima URL valid dengan protokol HTTP atau HTTPS.

## 8. Urgency

Field existing `tickets.urgency` digunakan. Tiket baru wajib memilih `low`, `medium`, atau `high`. Kolom tetap nullable agar tiket lama kompatibel.

## 9. Payload Requester

Payload tervalidasi:

- `request_category`
- `application_id`
- `title`
- `description`
- `affected_url`
- `reference`
- `attachments`
- `urgency`

Field requester identity, status, workflow, technical category, priority, PIC, assignment, approval, role, dan internal notes tetap ditolak. Endpoint generic ticket juga menolak actor Requester agar kontrak khusus tidak dapat dilewati.

## 10. Database Changes

Migration additive `2026_07_29_000006_add_request_category_to_tickets.php` menambahkan string nullable dan indexed `tickets.request_category`. Tidak ada row lama yang diubah. Verifikasi lokal menemukan dua tiket lama tetap null.

## 11. Form Request

`StoreRequesterTicketRequest` menangani normalization, whitelist kategori/urgency, active application validation, conditional URL, upload validation existing, forbidden-field validation, trim, dan penolakan HTML pada judul/deskripsi.

## 12. Ticket Creation Service

`RequesterTicketService` menyimpan kategori, application, urgency, dan URL nullable. Idempotency hash juga mencakup ketiga field baru dan URL nullable. Workflow, requester, status awal, audit actor, attachments, dan notifications tetap ditentukan backend.

## 13. Requester List

Daftar menampilkan nomor, judul, kategori Requester, sistem, urgency, public status, informasi penanganan, dan tanggal pengajuan. Tiket lama mendapat fallback yang aman.

## 14. Requester Detail

Detail menampilkan kategori pengajuan, nama sistem, judul/deskripsi, Link Submission, referensi, lampiran publik, urgency, status, lokasi kantor/divisi, informasi penanganan, tanggal, dan timeline existing. Nilai null legacy tidak menyebabkan crash.

## 15. PIC Handling Information

Handling dihitung backend dari assignment `primary|secondary` dengan `is_current=true`, `ended_at=null`, dan assignee aktif.

- Tanpa primary: `Sedang dalam proses penanganan oleh Supervisor IT.`
- Dengan primary: `Ditangani oleh [Nama] — [Role publik].`
- Selesai: `Penanganan diselesaikan oleh [Nama].`
- Ditolak/dibatalkan: pesan terminal yang tidak menyatakan masih aktif ditangani.

Role publik: IT Support, IT Developer, Supervisor IT, atau Tim IT.

## 16. Public Resource

`TicketResource` menambahkan:

- `request_category.value|label`
- application existing
- urgency existing
- `handling.state|message|primary_pic|secondary_pics`

Object handling hanya berisi ID, nama, dan label role publik. Email, phone, notes, reason, assigner, workload, permission, dan assignment history tidak dikembalikan pada object tersebut.

## 17. Authorization

Requester tetap hanya melihat tiket miliknya. Requester lain mendapat 403. Requester tidak dapat memakai endpoint create generic, menentukan requester lain, workflow, status, technical category, priority, atau assignment.

## 18. Security

- Active application divalidasi backend.
- Category dan urgency memakai whitelist.
- URL hanya HTTP/HTTPS.
- Upload policy existing tidak diturunkan.
- HTML pada judul/deskripsi ditolak.
- Requester update tidak dapat mengubah kategori, sistem, urgency, status, workflow, atau PIC.
- Handling tidak membocorkan data privat PIC.

## 19. Legacy Compatibility

Tiket lama boleh memiliki request category, application, dan urgency null. Resource/UI memakai fallback. Dynamic Workflow Engine, assignment eligibility lintas sistem, snapshots, role mapping, dan ticket statuses tidak diubah.

## 20. Backend Tests

Coverage mencakup kategori, conditional URL, active system, invalid system, urgency, payload security, HTML, endpoint bypass, seeder idempotence, office-based Requester, idempotency, handling primary/secondary, inactive assignment, reassignment, role label, no-leak, authorization, dan legacy null fields.

Hasil final: 377 test, 2.212 assertions, PASS. Pint PASS.

## 21. Frontend Tests

Project tidak memiliki Vitest/Jest/Testing Library/Playwright/Cypress atau script `npm test`. Verifikasi yang tersedia:

- `npm run typecheck`: PASS
- `npm run build`: PASS

Tidak ada hasil automated component test yang dikarang.

## 22. Responsive

Form menggunakan layout satu kolom pada mobile, selector kategori/urgency menjadi tiga kolom mulai `sm`, padding responsif, action stack pada mobile, serta input/select full width. Verifikasi visual manual pada lima viewport tetap pending karena tidak tersedia browser automation untuk flow ini.

## 23. Accessibility

Kategori dan urgency memakai fieldset, legend, dan radio native keyboard-accessible. Input/select shared component menghubungkan label serta error dengan field. Loading/error text tersedia dan status handling memakai `role=status`.

## 24. Notification

Notifikasi Supervisor untuk tiket baru memuat nomor, kategori, sistem, urgency, judul, dan nama Requester. Tidak ada email, phone, notes, atau metadata internal. Link notifikasi Requester menggunakan canonical `/user/tickets/{id}`.

## 25. File yang Berubah

File utama revisi:

- `backend/app/Enums/RequesterCategory.php`
- `backend/database/migrations/2026_07_29_000006_add_request_category_to_tickets.php`
- `backend/database/seeders/ApplicationSystemSeeder.php`
- `backend/database/seeders/DatabaseSeeder.php`
- `backend/app/Http/Requests/Api/V1/StoreRequesterTicketRequest.php`
- `backend/app/Http/Requests/Api/V1/UpdateTicketRequest.php`
- `backend/app/Services/RequesterTicketService.php`
- `backend/app/Models/Ticket.php`
- `backend/app/Http/Resources/Api/V1/TicketResource.php`
- `backend/app/Http/Controllers/Api/V1/RequesterTicketController.php`
- `backend/app/Http/Controllers/Api/V1/TicketController.php`
- `backend/app/Listeners/TicketNotificationSubscriber.php`
- `frontend/src/pages/user/CreateTicket.tsx`
- `frontend/src/pages/user/TicketHistory.tsx`
- `frontend/src/pages/user/TicketDetail.tsx`
- `frontend/src/services/ticketService.ts`
- `frontend/src/presentation.ts`
- test backend terkait Requester, seeder, handling, idempotency, workflow, dan legacy validation.

## 26. Error yang Ditemukan

- Requirement lama dan baru bertentangan; diselesaikan dengan field Requester category terpisah dari category teknis.
- Database lokal belum memiliki application; diselesaikan dengan seeder canonical khusus.
- Idempotency hash lama tidak mencakup klasifikasi baru; diperbaiki.
- Endpoint generic memungkinkan bypass Requester; ditutup dengan 403.
- Fixture lama masih membuat Requester lewat endpoint generic/five-field; diselaraskan ke endpoint baru.
- Resource/UI sebelumnya mengasumsikan division/category selalu tersedia; null guards Office revision tetap dipertahankan.
- Workflow health check lokal gagal karena tidak ada workflow aktif dan feature flag false.

## 27. Pending

- Manual browser viewport matrix 1440x900, 1366x768, 768x1024, 390x844, dan 360x800.
- Workflow health check memerlukan workflow aktif pada environment yang ditujukan untuk workflow aktif. Workflow tidak diaktifkan dalam revisi ini.

## 28. Risiko

- `request_category`, application, dan urgency wajib di API tetapi nullable di DB demi legacy compatibility; import/direct DB writes harus tetap dikontrol.
- Sistem canonical dipertahankan berdasarkan code. Nama edit Admin tidak ditimpa, sehingga label lokal dapat berbeda dari canonical setelah edit yang disengaja.
- Public handling bergantung pada disiplin assignment service karena database belum memiliki partial unique constraint current primary lintas MySQL/SQLite.
- Visual/E2E interaction belum memiliki automated runner.

## 29. Status

**PASS WITH PENDING**

Fitur dan seluruh automated regression lulus. Pending hanya workflow environment existing dan manual visual/browser matrix.

## Manual Test Matrix

Skenario Request/HRIS/no URL/MEDIUM tercakup oleh feature test dan berhasil. Error Bug/BPR CORE/no URL ditolak; URL HTTPS/HIGH diterima. Other/COMPANY PROFILE/no URL/LOW diterima. Handling tanpa PIC dan dengan PIC aktif tercakup oleh feature test. Browser manual tetap pending dan tidak diklaim selesai.
