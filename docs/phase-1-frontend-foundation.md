# Phase 1: Frontend Foundation and Portability

## Masalah yang diselesaikan

- Build tidak lagi bergantung pada `.figma/make/site.json`, environment Figma, port sandbox, atau plugin preview Figma.
- Shell HTML kini memiliki metadata statis yang valid.
- Diagnostic TypeScript dari unused symbol dan callback label Recharts diselesaikan tanpa `any`, `@ts-ignore`, atau menonaktifkan strict checking.
- Script install, development, type-check, format, build, dan preview dibuat standar.
- Route tidak dikenal menampilkan halaman 404.
- Shell mobile memiliki drawer, backdrop, dan perilaku close-on-navigation.
- Komponen form, modal, tombol ikon, tabel interaktif, dan focus state memperoleh perbaikan aksesibilitas dasar.

## Keputusan teknis

- Vite memakai plugin React dan Tailwind saja, dengan alias `@` tetap dipertahankan.
- Prettier menggantikan `oxfmt` agar command sesuai kontrak fase.
- `TicketRepository` menjadi batas akses data. `mockTicketRepository` tetap membaca `data.ts`, sehingga UI tidak perlu dimigrasikan sekaligus.
- `ticketService` menerima repository melalui factory agar implementasi API dapat diinjeksi kemudian.
- Hanya halaman Riwayat Tiket yang memakai service/repository sebagai proof of concept.
- `BrowserRouter` dipertahankan. Server production nantinya harus mengarahkan fallback route ke `index.html`.

## File penting

- `vite.config.ts`, `index.html`, `package.json`, `tsconfig.json`
- `.gitignore`, `.env.example`, `.prettierrc.json`, `.prettierignore`
- `src/api/*`, `src/config/env.ts`, `src/repositories/*`, `src/services/*`
- `src/App.tsx`, `src/components/Layout.tsx`, `src/components/ui.tsx`
- `src/pages/NotFound.tsx`, `src/pages/user/TicketHistory.tsx`

## Struktur baru

```text
src/
├── api/
├── config/
├── context/
├── hooks/
├── lib/
├── repositories/
├── services/
└── types/
```

## Hasil verifikasi

- `corepack pnpm install --frozen-lockfile`: berhasil.
- `corepack pnpm typecheck`: berhasil tanpa diagnostic.
- `corepack pnpm format:check`: berhasil.
- `corepack pnpm build`: berhasil; tersisa warning ukuran initial bundle 801.22 kB (222.55 kB gzip).
- Login diverifikasi pada viewport mobile 390 × 844: panel tunggal, label form terhubung, dan tidak ada horizontal overflow.
- Browser automation terlepas saat melakukan aksi klik. Dashboard dua role, halaman tabel, halaman form, dan shell desktop/mobile setelah login belum dapat dinyatakan terverifikasi secara visual pada sesi ini.

### Verifikasi lanjutan 14 Juli 2026

Verifikasi memakai development server dan navigasi SPA dalam sesi demo yang sama. Reload atau akses URL langsung kembali ke Login karena autentikasi demo hanya disimpan dalam React state. Browser Chrome juga menolak override viewport: permintaan desktop 1440 x 900 terbaca 1280 x 585 dan permintaan mobile 390 x 844 terbaca 1270 x 529.

| Route                          | Viewport   | Hasil        | Catatan                                                                              |
| ------------------------------ | ---------- | ------------ | ------------------------------------------------------------------------------------ |
| `/` (Login)                    | 1270 x 529 | Berhasil     | Render penuh, console bersih, tanpa overflow; override mobile tidak diterapkan alat. |
| `/user/dashboard`              | 1280 x 585 | Berhasil     | Dashboard User; shell, header, dan sidebar tampil.                                   |
| `/user/create-ticket`          | 1280 x 585 | Berhasil     | Halaman form render tanpa runtime exception.                                         |
| `/user/tickets`                | 1280 x 585 | Berhasil     | Halaman tabel render tanpa overflow document.                                        |
| `/user/tickets/:id`            | -          | Belum visual | Tidak ada link detail pada tabel; reload URL menghapus state login demo.             |
| `/user/uat`                    | 1280 x 585 | Parsial      | URL berubah tanpa console error, tetapi heading belum memberi bukti kuat.            |
| `/supervisor/dashboard`        | 1280 x 585 | Berhasil     | Dashboard role kedua; shell tampil dan console bersih.                               |
| `/supervisor/validation-queue` | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/itlead/dashboard`            | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/itlead/triage`               | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/itlead/priority`             | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/pic/dashboard`               | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/pic/workspace`               | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/pic/rca`                     | 1280 x 585 | Parsial      | URL berubah tanpa console error; heading masih menampilkan workspace.                |
| `/pic/testing`                 | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/qa/dashboard`                | 1280 x 585 | Belum visual | Sesi terlepas dan state kembali ke Login saat pergantian role.                       |
| `/qa/testing`                  | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/manager/approval`            | 1280 x 585 | Berhasil     | Render penuh, termasuk tabel approval.                                               |
| `/sla-monitoring`              | -          | Belum visual | Navigasi terhenti saat sesi browser terlepas.                                        |
| `/notifications`               | 1280 x 585 | Berhasil     | Render penuh tanpa runtime exception.                                                |
| `/executive/dashboard`         | -          | Belum visual | Kontrol terlepas saat memilih akun Executive.                                        |
| `/executive/statistics`        | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/admin/console`               | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/admin/users`                 | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/admin/divisions`             | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/admin/sla-rules`             | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/admin/escalation`            | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `/admin/audit-log`             | -          | Belum visual | Tidak tercapai sebelum sesi terlepas.                                                |
| `*` (Not Found)                | -          | Belum visual | Route fallback lulus type-check/build, belum dirender dalam sesi login.              |

Tidak ada error level `error` pada console dan tidak ada horizontal overflow document pada route yang berhasil diperiksa. Keterbatasan browser dicatat sebagai keterbatasan verifikasi, bukan defect aplikasi; source aplikasi tidak diubah untuk mengakalinya. Phase 1 belum dinyatakan selesai karena shell pascalogin 390 x 844 dan seluruh route belum memperoleh bukti render visual.

## Sengaja belum dilakukan

- Laravel backend, MySQL, Sanctum, authentication, dan authorization nyata.
- Implementasi RBAC serta enforcement akses Executive.
- Integrasi API atau migrasi seluruh halaman dari data mock.
- Perubahan workflow, SLA dummy, deployment, monitoring, closing, atau Knowledge Base.
- Redesign visual dan penambahan state-management library.
