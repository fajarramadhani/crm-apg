---
name: frontend-dashboard-ui-ux-analysis
description: Analisis UI/UX dari halaman dashboard aplikasi APG CRM
metadata:
  type: reference
---

# Analisis UI/UX Dashboard APG CRM

> Analisis berdasarkan kode sumber frontend (React + Tailwind CSS v4) untuk berbagai dashboard peran pengguna.

## Ringkasan Keseluruhan

Aplikasi ini adalah sistem IT Service Management (ITSM) berbasis web dengan pendekatan **role-based UI/UX**. Setiap peran pengguna (PIC, Supervisor IT, IT Lead, QA, Manager, dll.) memiliki *dashboard* yang disesuaikan dengan alur kerja dan kebutuhan operasionalnya masing-masing.

---

## 1. Tema & Desain Visual (DESIGN SYSTEM)

### Palet Warna:
*   **Warna Brand Utama:** Biru tua (`#0F2554`) dan biru medium (`#1E3A8A`). Ini konsisten dengan halaman login dan menciptakan identitas visual yang kuat dan profesional.
*   **Warna Aksen/Warnanya:** Menggunakan skema warna Tailwind standar secara luas (`red`, `amber`, `green`, `purple`, `cyan`, `teal`). Ini memudahkan pemetaan status secara intuitif.
*   **Dashboard Warna:** Warna untuk *badge* dan *KPI card* didefinisikan secara terpusat di `presentation.ts` (e.g., `getStatusColor`, `getPriorityColor`), memastikan konsistensi di seluruh aplikasi.

### Tipografi:
*   Penggunaan font sans-serif default Tailwind. Font-weight bervariasi dari `medium` hingga `black` untuk menciptakan hierarki visual yang jelas pada judul, label, dan nilai angka.

### Elemen Desain:
*   **Rounded Corners:** Elemen UI konsisten menggunakan border-radius (`rounded-xl`, `rounded-2xl`). Ini memberi kesan modern dan profesional.
*   **Bayangan (Shadows):** Penggunaan bayangan lembut (`shadow-sm`, `shadow-md`, `shadow-xl`) membantu menciptakan kedalaman dan memisahkan elemen kartu (*cards*) dari latar belakang.
*   **Garis Bawah Pembatas:** `SectionCard` dan tabel menggunakan `border-b` untuk memisahkan header dan konten.

---

## 2. Layout & Navigasi (LAYOUT)

### Sidebar Navigasi:
*   **Struktur:** Vertikal, berada di sisi kiri, dengan logo "APG CRM" di bagian atas.
*   **Dinamis Berdasarkan Peran:** Navigasi disesuaikan sepenuhnya dengan `role` pengguna (didefinisikan di `Layout.tsx` sebagai `NAV_ITEMS`). Ini sangat penting karena setiap peran memiliki akses yang berbeda.
*   **Item Navigasi:** Menggunakan ikon emoji dan teks. Item yang aktif ditandai dengan latar belakang putih semi-transparan (`bg-white/20`).
*   **Kolapsibilitas:** Sidebar bisa dilipat (`md:w-16` -> `md:w-60`) untuk menghemat ruang horizontal, terutama berguna pada layar yang lebih sempit. Ikon tetap terlihat saat dilipat.
*   **Responsivitas:** Pada mobile, sidebar berubah menjadi overlay yang dapat dibuka/menutup dengan tombol hamburger di header.

### Header Atas:
*   **Konsisten:** Selalu ada, berwarna putih dengan bayangan bawah untuk pemisahan visual.
*   **Isi:** Nama aplikasi ("APG Enterprise Internal CRM"), tombol toggle sidebar, dan area profil pengguna (avatar, nama, peran) di sebelah kanan, dilengkapi dengan notifikasi (jika relevan) dan tombol logout. Ini menyediakan aksen aksi global yang mudah diakses.

### Konten Utama:
*   **Wrapper:** Konten halaman berada dalam `<main>` dengan padding (`p-4 sm:p-6`) dan lebar maksimum terbatas (`max-w-[1400px] mx-auto`) untuk mencegah teks dan elemen melebar berlebihan pada layar besar.

---

## 3. Analisis Spesifik Dashboard

### a. Supervisor IT Dashboard (`SupervisorDashboard.tsx`)
*   **Tujuan:** "Pusat kendali operasional tiket lintas cabang & divisi". Ini adalah *hub* utama untuk supervisor.
*   **Desain:** Sangat informatif dan padat.
    *   **Header:** Jelas dengan judul besar dan tombol aksi "Lihat Seluruh Tiket".
    *   **Kartu Ringkasan (9 Cards):** Grid 9 kartu statistik (grid-cols-9 pada desktop). Setiap kartu mewakili status tiket yang berbeda (Baru, Dianalisis, Tanpa PIC, Ditangani, dll.). Kartu-karti ini bersifat interaktif (clickable) dan mengarahkan pengguna ke daftar tiket yang sudah di-filter.
    *   **Bucket Aksi Berwarna:** Empat `SupervisorTicketTable` disusun dalam sekatan (*grid* dan *stack*) dengan *badge* warna di samping judulnya (biru, merah, oranye, rose) untuk membedakan kategori tiket (Butuh Tindakan, Prioritas Tinggi, Tanpa PIC, Overdue, Approval). Ini adalah contoh penggunaan warna untuk mengkategorikan informasi yang sama sekali tidak membingungkan.
*   **UX:** Sangat baik untuk peran supervisor yang butuh panorama cepat. Namun, grid 9 kolom bisa terasa padat dan membutuhkan gulir horizontal pada lebar tertentu.

### b. PIC Dashboard (`PICDashboard.tsx`)
*   **Tujuan:** Menampilkan tiket yang aktif ditugaskan kepada PIC tersebut.
*   **Desain:** Lebih sederhana dan fokus.
    *   **Header:** Menggunakan komponen `PageHeader` yang konsisten.
    *   **KPI Cards (4 Cards):** `Assignment Aktif`, `Critical`, `High`, `Lewat Deadline`. Warna berbeda (biru, merah, amber) untuk visibilitas cepat.
    *   **Assignment Terbaru:** Sebuah `SectionCard` berisi daftar tiket terbaru dalam bentuk *list* dengan tombol klik untuk navigasi ke *workspace*.
*   **UX:** Fokus dan tidak berantakan. Cocok untuk peran pelaksana yang mungkin tidak membutuhkan pandangan penuh sekaligus. Namun, tombol di dalam list hanya mengarahkan ke satu halaman (`workspace`), bukan ke detail tiket spesifik, yang mungkin mengurangi efisiensi.

### c. QA Dashboard (`QADashboard.tsx`)
*   **Tujuan:** Kelola antrian pengujian, eksekusi test case, dan verifikasi kualitas.
*   **Desain:** Kompleks dan proses-orientir.
    *   **Header:** Konsisten dengan deskripsi singkat.
    *   **KPI Cards (4 Cards):** `Antrian Testing`, `QA Rework`, `Lulus QA`, `Total Ditugaskan`. Dilengkapi dengan ikon emoji.
    *   **Layout Dua Kolom:** Bagian kiri (`lg:col-span-2`) menampilkan daftar tiket testing dengan tombol aksi (`Mulai testing`, `Uji Sekarang`). Bagian kanan menampilkan `Status Testing` (visualisasi dengan *dot* warna) dan `Panduan Proses QA` (panduan langkah demi langkah).
    *   **Tabel Terakhir:** `Semua Tiket dalam Lingkup QA Anda` menggunakan komponen `Table`, `TR`, `TD` generik, menunjukkan adopsi pola desain konsisten.
*   **UX:** Sangat baik. Memandu pengguna dengan panduan proses yang jelas dan mengelompokkan informasi dengan logis. Ikon emoji dan *dot* warna meningkatkan skanabilitas.

### d. Analytics Dashboard (`AnalyticsDashboard.tsx`)
*   **Tujuan:** Menampilkan laporan statistik berdasarkan peran.
*   **Desain:** Berbasis data dan filter.
    *   **Header:** Judul dinamis dengan peran. Tombol ekspor CSV untuk peran tertentu.
    *   **Filter Bar:** Grup input tanggal dan tombol "Terapkan Filter".
    *   **Konten Data:** Grid kartu (`Volume Tiket`, `SLA Compliance`, `Kualitas & Rework`, `Deployment`, dll.) yang menampilkan metrik numerik dalam format daftar.
*   **UX:** Baik untuk tujuan analitik. Namun, tampilannya terasa berbeda (kurang menggunakan komponen UI khusus seperti `SectionCard`) dan lebih sederhana dibandingkan dashboard lain, seperti menggunakan border-radius `rounded-lg` dan bayangan standar Tailwind dibandingkan `rounded-xl`. Ini bisa menyebabkan sedikit ketidakkonsistenan estetika.

---

## 4. Komponen UI Kunci dan Konsistensi (UI COMPONENTS)

Beberapa komponen reusable didefinisikan di `components/ui.tsx` dan digunakan melintasi halaman:

*   **KPICard:** Selalu ada di dashboard. Desain konsisten dengan gradien warna, ikon, judul, dan nilai. Baik untuk visualisasi metrik cepat.
*   **SectionCard:** Pembungkus umum untuk grup konten dengan opsi judul, aksi, dan kelas khususu. Ini adalah fondasi untuk kartu konten.
*   **StatusBadge & PriorityBadge:** Badge kecil dengan warna yang didasarkan pada status/prioritas. Meningkatkan skanabilitas tabel dan daftar.
*   **SLAIndicator:** Visualisasi bar yang tipis untuk menunjukkan waktu tersisa sampai batas SLA. Sangat berguna untuk *at-a-glance awareness*.
*   **PageHeader:** Wrapper konsisten untuk judul halaman dan subtitel.
*   **Button:** Dukungan varian (primary, secondary, danger, ghost, warning, success), ukuran, dan status loading. Sangat fleksibel.
*   **Table, TR, TD:** Komponen dasar untuk membuat tabel yang konsisten.
*   **Modal:** Sistem modal dengan manajemen fokus pusat dan backdrop. Baik untuk aksesibilitas.
*   **Toast:** Notifikasi p pop-up yang muncul di bagian bawah kanan layar untuk umpan balik aksi (sukses/error).
*   **EmptyState:** Tampilan yang ramah untuk kondisi data kosong.
*   **ActivityTimeline:** Timeline vertikal untuk audit trail/log aktivitas.
*   **Tabs:** Navigasi berbasis tab sederhana.

**Kesimpulan Komponen:** Aplikasi ini memiliki sistem komponen yang cukup baik dan konsisten. Komponen-komponen kunci digunakan secara luas, yang menciptakan rasa "satu sistem desain" yang kuat. Namun, terdapat beberapa ketidakkonsistenan kecil (misalnya, gaya `analyticsDashboard` yang sedikit berbeda).

---

## 5. Responsivitas

*   **`Tailwind CSS` Responsif:** Framework ini dengan baik digunakan untuk semua tingkatan layar (`sm`, `md`, `lg`, `xl`).
    *   Grid KPI cards beralih dari 2 kolom (`grid-cols-2`) pada mobile ke 4 kolom (`lg:grid-cols-4`) pada desktop.
    *   Layout dua kolom pada `QADashboard` beralih menjadi satu kolom pada mobile (`grid-cols-1`).
    *   Sidebar collabsi menjadi ikon-only pada `md`.
*   **Masalah Potensial:** Grid 9 kolom pada `SupervisorDashboard` bisa menimbulkan masalah tampilan pada resolusi tengah.

---

## 6. Aksesibilitas (Accessibility)

*   Beberapa langkah baik telah diambil:
    *   Label dan `htmlFor` yang jelas pada input.
    *   `aria-label` pada tombol ikon dan interaksi.
    *   `role="alert"` pada notifikasi error.
    *   `aria-live` pada notifikasi Toast.
    *   Fokus managemen pada `Modal`.
    *   `tabindex` pada baris tabel yang dapat diklik.
    *   Tombol 'Lewati ke konten utama' untuk navigasi pogoing keyboard.

---

## 7. Kesimpulan dan Rekomendasi

### Kekuatan:
1.  **Konsistensi Tema:** Skema warna biru brand yang konsisten dan penggunaan warna-status yang intuitif.
2.  **UX Berbasis Peran:** Setiap dashboard sangat relevan dengan tugas peran penggunanya.
3.  **Sistem Komponen Solid:** Komponen reusable yang baik meningkatkan pengembangan dan konsistensi.
4.  **Fokus pada Data & Keputuhan Operasional:** Dashboard penuh dengan metrik dan tindakan yang relevan.
5.  **Aksesibilitas Dasar:** Beberapa praktik terbaik aksesibilitas telah diterapkan.

### Rekomendasi untuk Peningkatan:
1.  **Konsistensi Analytics Dashboard:** Seragamkan gaya visual `AnalyticsDashboard` dengan komponen lain (gunakan `SectionCard`, `rounded-xl`).
2.  **Optimalkan Grid 9 Kolom:** Pertimbangkan pendekatan yang lebih fleksibel untuk kartu statistik supervisor agar tidak terlihat sempit atau memaksa scroll horizontal.
3.  **Perbaiki Navigasi dari KPI List (PIC):** Pertimbangkan membuat setiap item daftar tiket di `PICDashboard` mengarahkan ke detail tiket, bukan hanya halaman umum.
4.  **Audit Aksesibilitas Penuh:** Lakukan audit aksesibilitas formal untuk memastikan semua interaksi dan komponen memenuhi standar WCAG.

