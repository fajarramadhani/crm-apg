# Penjelasan Environment pada CI MySQL

Tanggal: 11 Agustus 2026
Project: APG CRM

## Ringkasan

Konfigurasi berikut dibuat untuk menjalankan automated test pada runner GitHub Actions yang sementara dan disposable:

```yaml
env:
  APP_ENV: testing
  APP_KEY: base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=
  DB_CONNECTION: mysql
  DB_HOST: 127.0.0.1
  DB_PORT: 3306
  DB_DATABASE: apg_crm_test
  DB_USERNAME: root
  DB_PASSWORD: ci-root-password
  CACHE_STORE: array
  MAIL_MAILER: array
  QUEUE_CONNECTION: sync
  SESSION_DRIVER: array
```

Nilai tersebut bukan credential staging atau production. MySQL dibuat sebagai service container sementara untuk satu CI job dan dihapus setelah job selesai.

## Fungsi Setiap Variable

### `APP_ENV=testing`

Menandai bahwa Laravel berjalan dalam mode automated testing.

Dampaknya:

- Memisahkan perilaku test dari staging/production.
- Mengizinkan command atau guard yang memang khusus testing.
- Mencegah test dianggap sebagai runtime production.

### `APP_KEY=base64:...`

Laravel membutuhkan application key yang formatnya valid untuk:

- Encryption service.
- Encrypted model casts.
- Cookie dan komponen framework tertentu.
- Bootstrapping aplikasi selama test.

Nilai ini adalah key dummy statis untuk CI. Nilai tersebut bukan key staging atau production dan tidak boleh digunakan untuk environment nyata.

Application key CI perlu stabil supaya test yang menggunakan encryption dapat berjalan deterministik dalam satu job. Menyimpan dummy testing key di workflow umumnya lebih aman daripada memakai production secret pada CI Pull Request.

### `DB_CONNECTION=mysql`

Memaksa test menggunakan driver MySQL, bukan SQLite. Tujuannya menangkap perbedaan antara SQLite dan database target, seperti:

- Enum dan constraint.
- Foreign key.
- Index dan unique constraint.
- JSON behavior.
- Migration syntax.
- Transaction dan locking behavior tertentu.

### `DB_HOST=127.0.0.1` dan `DB_PORT=3306`

GitHub Actions menjalankan MySQL service container dan memetakan port container ke runner sementara. Laravel mengakses service tersebut melalui loopback runner.

Alamat ini bukan alamat database staging atau production.

### `DB_DATABASE=apg_crm_test`

Nama database disposable yang hanya digunakan oleh automated test.

Suffix `_test` membantu mencegah kebingungan dengan database aplikasi nyata.

### `DB_USERNAME=root` dan `DB_PASSWORD=ci-root-password`

Credential tersebut hanya cocok dengan MySQL service container yang dibuat dalam job CI yang sama.

Karakteristiknya:

- Bukan credential infrastructure APG.
- Tidak dapat digunakan untuk mengakses database staging atau production.
- Hanya hidup selama runner/job aktif.
- Database dibuang saat job selesai.
- Password sengaja bersifat test-only dan tidak diperlakukan sebagai secret production.

Menggunakan GitHub Secret untuk credential disposable seperti ini tidak selalu diperlukan. Secret juga sering tidak tersedia pada Pull Request dari fork, sedangkan job perlu dapat membuat database test sendiri.

### `CACHE_STORE=array`

Menggunakan cache in-memory selama test.

Tujuannya:

- Tidak membutuhkan Redis atau cache server lain.
- Tidak meninggalkan state antartest/job.
- Membuat test lebih cepat dan terisolasi.

### `MAIL_MAILER=array`

Menahan email di memory selama test dan tidak mengirim email nyata.

Tujuannya:

- Tidak menghubungi SMTP production.
- Tidak mengirim OTP atau notifikasi ke penerima nyata.
- Memungkinkan test melakukan assertion terhadap email.

### `QUEUE_CONNECTION=sync`

Menjalankan queued job secara langsung pada proses test.

Tujuannya:

- Tidak memerlukan queue worker eksternal.
- Mempermudah assertion dan failure detection.
- Membuat test deterministik.

Test tertentu tetap dapat menggunakan `Queue::fake()` atau memanggil job secara eksplisit untuk menguji queue behavior.

### `SESSION_DRIVER=array`

Menyimpan session hanya di memory selama test.

Tujuannya:

- Tidak membutuhkan table/session service untuk setiap test.
- Tidak menyimpan session di disk.
- Mengisolasi session per proses test.

## Mengapa Ada `touch .env`

Command:

```yaml
- run: touch .env
```

hanya membuat file `.env` kosong di runner CI.

Command tersebut tidak menulis variable di atas ke `.env`. Variable diberikan langsung oleh GitHub Actions sebagai process environment.

File kosong ini sebelumnya diperlukan agar beberapa proses Laravel/Composer package discovery menemukan struktur environment yang diharapkan tanpa menyalin `.env.example` yang berisi default development.

Urutan sumber konfigurasi pada job tersebut adalah:

```text
GitHub Actions job env
        -> process environment
        -> Laravel config/env resolution
```

Bukan:

```text
credential ditulis ke .env
        -> .env dikomit
```

Runner dan file `.env` sementara tersebut dibuang setelah job selesai.

## Mengapa Menjalankan `migrate:fresh`

Workflow menjalankan:

```bash
php artisan migrate:fresh --seed --force
php artisan migrate:rollback --step=1 --force
php artisan migrate --force
php artisan migrate:status
php artisan migrate:fresh --force
```

Tujuannya:

1. Membuktikan seluruh schema dapat dibuat dari database kosong.
2. Membuktikan seeder dapat berjalan pada MySQL.
3. Membuktikan migration terakhir dapat di-rollback.
4. Membuktikan migration dapat diterapkan ulang.
5. Membuktikan status migration konsisten.
6. Menyiapkan database kosong kembali sebelum full test suite.

`migrate:fresh` aman pada konteks ini karena database `apg_crm_test` dibuat khusus untuk job disposable.

Command yang sama tidak boleh dijalankan pada database staging retained atau production karena akan menghapus seluruh tabel.

## Security Assessment

Konfigurasi tersebut tidak membocorkan secret APG apabila seluruh asumsi berikut benar:

- MySQL adalah service container disposable milik CI job.
- Credential tidak digunakan ulang pada environment lain.
- Runner bersifat ephemeral.
- Database tidak dibuka sebagai managed database publik.
- Tidak ada production dump atau data sensitif yang dimasukkan ke database CI.
- `APP_KEY` hanya dummy testing key.
- File `.env` tetap di-ignore dan tidak di-upload sebagai artifact.

Hal yang memang tidak boleh ditulis langsung dalam workflow:

```text
APP_KEY production
DB_PASSWORD staging/production
FONNTE_TOKEN nyata
FONNTE_WEBHOOK_SECRET nyata
SMTP password nyata
private key
production database dump
```

Nilai nyata harus menggunakan GitHub Secrets, environment protection, OIDC, atau secret manager sesuai arsitektur deployment.

## Status Workflow Saat Ini

File `.github/workflows/ci.yml` telah dihapus dari HEAD feature branch melalui commit:

```text
0cf635a ci: remove GitHub Actions workflow
```

Akibatnya, konfigurasi di atas tidak lagi berada pada HEAD feature branch. Penjelasan ini mendokumentasikan fungsi konfigurasi historis tersebut.

Release tag RC1 tetap menunjuk baseline sebelumnya dan tidak dipindahkan:

```text
v1.0.0-rc.1 -> 6a04506a9b35656356aa4bafb194ac12219c65fa
```

## Kesimpulan

Environment pada workflow tersebut adalah konfigurasi test disposable agar Laravel dapat menjalankan migration dan full test suite terhadap MySQL 8.4. Nilainya bukan credential staging atau production. `touch .env` hanya membuat file kosong sementara dan tidak menyimpan credential ke repository.
