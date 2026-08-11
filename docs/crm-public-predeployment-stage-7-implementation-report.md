# LAPORAN IMPLEMENTASI TAHAP 7 - PRA-DEPLOYMENT CRM PUBLIK

## 1. Ringkasan

Tahap 7 menutup blocker kode untuk attachment tiket dan menambahkan alat UAT/migrasi yang aman. Status keseluruhan tetap **Belum siap deployment** karena validasi lingkungan produksi-like belum tersedia.

Tidak dilakukan commit, push, merge, rebase, reset, stash, clean, force-push, atau deployment. Seluruh perubahan lokal Tahap 3-6 dan pekerjaan existing lain dipertahankan.

## 2. Private Attachment Terpusat

`TicketAttachmentService` sekarang menjadi satu-satunya jalur penyimpanan attachment baru untuk:

- Requester authenticated dan public submission.
- Generic ticket attachment.
- PIC workspace, development, UAT rework, dan release evidence.
- QA evidence.
- Requester dan public UAT evidence.

Kontrol yang diterapkan:

- Menolak disk bernama `public` dan disk dengan `visibility=public`.
- Nama storage berupa UUID tanpa ekstensi dari nama klien.
- Nama asli dibersihkan dari path dan control character.
- Nama dengan executable/active-content extension, termasuk double extension, ditolak sebelum storage.
- MIME diambil dari server-side file inspection.
- Visibility `requester_visible` dinormalisasi menjadi `requester`.
- Insert metadata dan audit callback berada dalam transaction.
- File dibersihkan bila metadata/audit gagal.
- Delete memindahkan file ke quarantine, menjalankan delete metadata/audit transaction, dan mengembalikan file bila transaction gagal.
- Response PIC/release memakai `TicketAttachmentResource` dan tidak mengekspos `disk` atau `path`.

Foreign-key cascade langsung melalui SQL tetap tidak menjalankan Eloquent filesystem hook. Production ticket deletion tidak tersedia, tetapi setiap maintenance/retention process yang menghapus tiket secara langsung wajib menghapus attachment fisik melalui service atau menjalankan orphan reconciliation.

## 3. Migrasi Legacy Public Attachment

Command baru:

```text
php artisan tickets:migrate-public-attachments --dry-run --limit=500
php artisan tickets:migrate-public-attachments --limit=500
php artisan tickets:migrate-public-attachments --rollback --ticket=<id>
```

Karakteristik:

- Batch limit `1..10000` dan optional ticket canary.
- Kandidat forward hanya metadata dengan `disk=public`.
- Copy private dan rollback backup diverifikasi SHA-256.
- Metadata dikunci sebelum perubahan.
- Public source dihapus hanya setelah metadata berhasil berubah.
- Kegagalan mengembalikan metadata dan membersihkan output parsial.
- Rerun melanjutkan record public yang tersisa.
- Rollback hanya memproses record yang memiliki backup khusus attachment ID.

Tidak ada attachment nyata yang dimigrasikan di workspace ini. Inventory, backup, canary, full run, dan restore drill wajib dilakukan pada database/storage target yang disetujui.

## 4. Fixture UAT

Command `stage7:seed-public-uat` membuat data synthetic public UAT dan active tracking URL secara repeatable. Command dibatasi ke environment local/testing, database disposable, dan confirmation phrase eksplisit.

Fixture terbukti repeatable pada SQLite test. Browser/SMTP UAT tidak dijalankan.

## 5. Hasil Validasi

```text
Full backend: 456 passed, 3,064 assertions
Targeted attachment/public: 51 passed, 389 assertions
Pint: passed
Composer validate/audit: passed, no advisory
Frontend typecheck/build/format check: passed
SQLite fresh migration: passed
SQLite rollback/reapply migration terakhir: passed
git diff --check: passed
```

Frontend command awal gagal karena `pnpm` tidak ada pada PATH. Validasi kemudian berhasil melalui `corepack pnpm`; ini bukan kegagalan aplikasi.

## 6. Blocker Environment

- MySQL 8/InnoDB dan client/service tidak tersedia.
- SMTP sandbox tidak tersedia.
- Reverse proxy/APM/CDN log tidak tersedia.
- Approved disposable browser backend/database tidak tersedia.
- Concurrency dua-process tidak dapat dibuktikan dengan SQLite.
- Antivirus/content scanning belum tersedia.
- React Router `7.18.1` masih memiliki advisory high yang memerlukan major `8.3.0`; mitigasi tetap non-RSC dan upgrade terpisah.

## 7. Keputusan

**Belum siap deployment.** Kode dan automated regression memenuhi gate lokal, tetapi production readiness tidak boleh dinyatakan sebelum MySQL concurrency, SMTP, proxy/APM redaction, manual browser UAT, dan migrasi attachment target selesai dengan bukti dan sign-off.
