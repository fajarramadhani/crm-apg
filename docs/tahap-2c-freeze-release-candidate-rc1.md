# Tahap 2C - Freeze Release Candidate RC1 APG CRM

Tanggal: 11 Agustus 2026
Project: APG CRM
Status akhir: **RC1 FROZEN - READY FOR STAGING PREPARATION**

## 1. Baseline Verification

Baseline kode yang diverifikasi dan dibekukan:

```text
6a04506a9b35656356aa4bafb194ac12219c65fa
fix(ci): update CommonMark security release
```

Commit utama integrasi WhatsApp/Fonnte yang berada dalam baseline:

```text
7f2e9a42146853e8c3bb89f2b9545ebe1189d312
feat(crm): stabilize Fonnte WhatsApp notifications
```

Branch sumber baseline:

```text
feature/fonnte-whatsapp-notification
```

Pada saat verifikasi baseline, branch feature lokal sinkron dengan remote dan baseline `6a04506` telah lulus CI lengkap.

Sebelum freeze terdapat satu laporan Tahap 2B yang belum dikomit. Laporan tersebut kemudian dikomit dan dipush secara terpisah:

```text
eac9a1013a07fcbf102cf6d271b6ba26eb61d125
docs(crm): add RC preparation validation report
```

Commit dokumentasi `eac9a10` tidak menjadi baseline RC. Release branch dan tag dibuat secara eksplisit dari `6a04506`, bukan dari HEAD feature branch setelah commit dokumentasi.

## 2. Release Branch

Nama release branch:

```text
release/v1.0.0-rc1
```

Branch dibuat secara eksplisit dari:

```text
6a04506a9b35656356aa4bafb194ac12219c65fa
```

Verifikasi lokal:

```text
release/v1.0.0-rc1^{commit}
= 6a04506a9b35656356aa4bafb194ac12219c65fa
```

Verifikasi remote:

```text
refs/heads/release/v1.0.0-rc1
= 6a04506a9b35656356aa4bafb194ac12219c65fa
```

Push status: **berhasil**.

Push dilakukan tanpa force dan upstream branch telah terpasang.

## 3. Release Tag

Annotated tag:

```text
v1.0.0-rc.1
```

Tag message:

```text
APG CRM v1.0.0 Release Candidate 1
```

Jenis object tag:

```text
tag
```

Object hash annotated tag remote:

```text
f6503439c8fda94904998d40d0db7c9586316bf2
```

Peeled commit target tag lokal dan remote:

```text
6a04506a9b35656356aa4bafb194ac12219c65fa
```

Push status: **berhasil**.

Hanya tag RC berikut yang dipush:

```text
refs/tags/v1.0.0-rc.1
```

Tag production final `v1.0.0` tidak dibuat.

## 4. Immutability Verification

Release branch dan annotated tag mengacu pada commit kode yang sama:

```text
release/v1.0.0-rc1
6a04506a9b35656356aa4bafb194ac12219c65fa

v1.0.0-rc.1^{}
6a04506a9b35656356aa4bafb194ac12219c65fa
```

Baseline RC1 dianggap immutable.

Tidak dilakukan:

- Rebase history besar.
- Squash 28 commit.
- Force push.
- Perubahan default branch.
- Merge PR #2.
- Deployment production.
- Pembuatan tag final `v1.0.0`.

## 5. CI Verification

Push release branch dan annotated tag tidak memicu workflow CI baru karena workflow repository hanya berjalan pada:

- Push ke `development`.
- Event `pull_request`.

Tidak dilakukan perubahan workflow hanya untuk memaksa run baru.

Baseline yang ditag adalah commit yang sebelumnya telah divalidasi penuh:

```text
Commit: 6a04506a9b35656356aa4bafb194ac12219c65fa
CI run: 31456221988
URL: https://github.com/fajarramadhani/crm-apg/actions/runs/31456221988
Conclusion: SUCCESS
```

Hasil baseline CI:

| Job | Hasil |
| --- | --- |
| `backend` | Lulus |
| `backend-mysql` | Lulus |
| `frontend` | Lulus |

Quality gate baseline:

- Backend SQLite: lulus.
- Backend MySQL 8.4: lulus.
- Laravel Pint: lulus.
- Composer validate: lulus.
- Composer audit: lulus.
- Runtime fixture rejection: lulus.
- Frontend frozen install: lulus.
- Frontend TypeScript: lulus.
- Prettier: lulus.
- Production build: lulus.
- Frontend dependency audit: lulus.
- Secret scan: lulus.

Job MySQL baseline secara khusus memverifikasi:

- MySQL 8.4 container initialization.
- Fresh migration dan seed.
- Rollback migration terakhir.
- Reapply migration.
- Migration status.
- Fresh migration ulang.
- Full backend test suite pada MySQL.

Commit dokumentasi Tahap 2B `eac9a10` memicu run PR terpisah:

```text
CI run: 31457773049
backend: lulus
backend-mysql: lulus
frontend: lulus
```

Run dokumentasi tambahan tersebut tidak mengubah baseline RC.

## 6. Pull Request Status

Pull Request:

```text
#2
feature/fonnte-whatsapp-notification -> development
https://github.com/fajarramadhani/crm-apg/pull/2
```

Status saat freeze:

- State: `OPEN`.
- Merged: tidak.
- Mergeable: `MERGEABLE`.
- Tidak dilakukan merge.

PR dipertahankan sebagai evidence review dan integrasi. Strategi merge akan ditentukan pada tahap berikutnya.

## 7. RC Metadata

```text
Version: v1.0.0-rc.1
Release branch: release/v1.0.0-rc1
Baseline commit: 6a04506a9b35656356aa4bafb194ac12219c65fa
Target integration branch: development
Status: Frozen for Staging/UAT
```

Baseline code headline:

```text
fix(ci): update CommonMark security release
```

Primary WhatsApp/Fonnte feature commit:

```text
7f2e9a42146853e8c3bb89f2b9545ebe1189d312
feat(crm): stabilize Fonnte WhatsApp notifications
```

## 8. Freeze Policy

RC1 tidak menerima:

- Fitur baru.
- Redesign.
- Refactor kosmetik.
- Eksperimen.
- Perubahan workflow baru.
- Dependency update yang tidak terkait blocker.

Perubahan setelah freeze hanya diperbolehkan untuk:

- Blocker staging.
- Bug hasil integrated testing atau UAT.
- Security issue.
- Regression.
- Deployment atau staging issue.
- Data integrity issue.

Setiap perubahan yang disetujui setelah freeze harus:

1. Memiliki issue atau evidence yang jelas.
2. Berupa perubahan minimal.
3. Menjalankan ulang seluruh quality gate relevan.
4. Menjalankan ulang MySQL CI bila backend, migration, queue, atau persistence berubah.
5. Menghasilkan RC berikutnya, misalnya `v1.0.0-rc.2`, dan tidak memindahkan tag `v1.0.0-rc.1`.

## 9. Outstanding Risks

Risiko yang masih harus ditangani pada staging, integrated testing, atau UAT:

### Infrastructure dan Operations

- Target hosting, domain, secret manager, durable private storage, dan central monitoring harus diverifikasi pada staging.
- Queue worker `notifications,default` harus dijalankan dan disupervisi.
- Scheduler heartbeat harus dimonitor.
- Backup dan restore drill belum memiliki evidence staging final.
- Reverse proxy harus meneruskan `/api` dan `/sanctum` dengan konfigurasi trusted proxy yang benar.

### WhatsApp/Fonnte

- Webhook custom header harus dibuktikan pada provider atau disuntikkan oleh reverse proxy/API gateway.
- Token, webhook secret, dan nomor tujuan harus berasal dari secret manager atau environment terlindungi.
- Live Fonnte send/callback perlu divalidasi ulang secara terkontrol pada staging.
- Queue backlog, failed jobs, device status, dan quota perlu monitoring.
- Pesan berstatus ambigu setelah timeout memerlukan review operasional sebelum retry manual.
- Record `pending` yang tidak menerima webhook memerlukan prosedur reconciliation operasional.

### Security dan Compliance

- Malware scanning attachment belum menjadi bagian dari aplikasi.
- Production identity bootstrap atau SSO lifecycle belum selesai.
- Retention, privacy classification, dan evidence deletion policy masih memerlukan approval.
- Full security review dan authorization review staging tetap diperlukan.

### Performance dan UAT

- Performance target harus diuji pada staging dengan data dan jaringan realistis.
- Query N+1 dan report materialization perlu dipantau pada volume realistis.
- Desktop, tablet, dan mobile UAT untuk seluruh role belum selesai.
- Business UAT sign-off dan IT release approval belum diberikan.

### Integration Branch

- PR #2 memiliki diff besar terhadap `development` karena membawa rangkaian 28 commit yang belum terintegrasi.
- Default GitHub branch masih divergen dari `development` dan bukan target PR ini.
- Strategi merge ke `development` harus direview tanpa mengubah history RC1.

## 10. Status Akhir

```text
RC1 FROZEN - READY FOR STAGING PREPARATION
```

Dasar status:

- Release branch remote berhasil dibuat.
- Annotated RC tag remote berhasil dibuat.
- Branch dan peeled tag menunjuk tepat ke baseline tervalidasi `6a04506`.
- Baseline memiliki CI hijau lengkap termasuk MySQL 8.4.
- Tidak dilakukan force push, merge, deployment, atau perubahan production.
- PR #2 tetap terbuka dan belum merge.

## 11. Status File Laporan

Laporan Tahap 2C ini dibuat setelah branch dan tag RC berhasil dipush, lalu disertakan dalam commit dokumentasi terpisah pada feature branch. File laporan tidak termasuk dalam baseline RC1 dan tidak mengubah `release/v1.0.0-rc1` maupun `v1.0.0-rc.1`.
