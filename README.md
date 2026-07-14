# APG CRM

Frontend prototype untuk APG Enterprise Internal CRM dan IT Service Management. Phase 1 mengubah export Figma Make menjadi aplikasi React yang portable tanpa mengubah alur bisnis atau desain utama.

## Teknologi

- React 19 dan TypeScript
- Vite 8
- Tailwind CSS 4
- React Router
- Recharts
- pnpm dan Prettier

## Requirement lokal

- Node.js 20 atau lebih baru
- Corepack aktif

## Menjalankan project

```bash
corepack pnpm install --frozen-lockfile
corepack pnpm dev
```

Vite menampilkan alamat lokal di terminal. Salin `.env.example` menjadi `.env.local` hanya bila perlu mengganti calon base URL API.

## Pemeriksaan

```bash
corepack pnpm typecheck
corepack pnpm format:check
corepack pnpm build
corepack pnpm preview
```

Gunakan `corepack pnpm format` untuk merapikan source dengan Prettier.

## Struktur utama

- `src/api`: kontrak client HTTP dan tipe response.
- `src/config`: pembacaan environment variable tervalidasi secara minimal.
- `src/components`: shell dan komponen UI reusable.
- `src/pages`: seluruh halaman dan route prototype.
- `src/repositories`: interface akses data dan implementasi mock.
- `src/services`: use case tipis di atas repository.
- `src/data.ts`: data mock lama yang masih dipakai sebagian besar halaman.
- `docs`: audit, arsitektur, kontrak, dan catatan fase implementasi.

## Status backend dan data

Backend Laravel, database MySQL, Sanctum, authentication, dan authorization belum tersedia. Login dan role switcher adalah simulasi demo, bukan mekanisme keamanan. Data tetap mock; hanya halaman Riwayat Tiket yang sudah membuktikan pola service/repository.

## Roadmap singkat

1. Foundation frontend dan portability.
2. Laravel API, MySQL, serta master data.
3. Sanctum, role, permission, dan pembatasan Executive.
4. Migrasi halaman secara bertahap dari mock repository ke API.
5. Pengujian workflow, observability, dan kesiapan produksi.
