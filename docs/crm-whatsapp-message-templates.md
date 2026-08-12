# Template Pesan dan Event WhatsApp Fonnte

Template final berupa plain-text yang dirender APG lalu dikirim melalui Fonnte `/send`. Tidak ada Meta template name, kategori Meta, language approval, app secret, token, atau credential dalam dokumen ini.

Sumber implementasi: `backend/config/whatsapp.php` dan `backend/app/Listeners/WhatsAppNotificationSubscriber.php`.

## Aturan rendering

- Placeholder berbentuk `{nama_variabel}`.
- HTML tag dan control character dibersihkan sebelum pesan disimpan.
- Nilai placeholder dibatasi panjangnya oleh sanitizer service.
- Nomor penerima tidak termasuk isi template dan tidak dicantumkan di dokumen ini.
- URL tracking hanya digunakan bila akses tracking requester tersedia.
- Template dirender sebelum job dikirim dan salinan hasil render disimpan pada outbox untuk audit.

## Event dan template final

| Event | Penerima | Template key | Pemicu |
| --- | --- | --- | --- |
| `ticket_created` | Requester | `ticket_created_requester` | `TicketSubmitted`, jika tracking URL tersedia |
| `ticket_created` | IT Support | `ticket_created_it_support` | `TicketSubmitted` |
| `ticket_assigned` | PIC | `ticket_assigned_pic` | `TicketAssigned` |
| `important_status_changed` | Requester | `important_status_requester` | Analysis started, revision requested, UAT assigned/approved, rejected, atau aksi requester publik ditolak |
| `ticket_completed` | Requester | `ticket_completed_requester` | `TicketClosed` |
| `sla_warning` | PIC atau fallback IT Support | `sla_recipient` | Scan SLA approaching/critical |
| `sla_breached` | PIC atau fallback IT Support | `sla_recipient` | Scan SLA breached |
| `test` | IT Support | `test_it_support` | CLI atau halaman admin |

## 1. `ticket_created_requester`

```text
Halo {requester_name}, permintaan Anda telah kami terima.
Nomor tiket: {ticket_number}
Kategori: {category}
Status: {status}
Pantau perkembangan tiket: {tracking_url}
```

Variabel: `requester_name`, `ticket_number`, `category`, `status`, `tracking_url`.

## 2. `ticket_created_it_support`

```text
Tiket baru {ticket_number}: {title}
Pemohon: {requester_name}
Cabang: {branch}
Buka: {internal_url}
```

Variabel: `ticket_number`, `title`, `requester_name`, `branch`, `internal_url`.

## 3. `ticket_assigned_pic`

```text
Halo {pic_name}, tiket {ticket_number} ditugaskan kepada Anda.
Judul: {title}
Prioritas: {priority}
Pemohon: {requester_name}
Cabang: {branch}
Buka tiket: {internal_url}
```

Variabel: `pic_name`, `ticket_number`, `title`, `priority`, `requester_name`, `branch`, `internal_url`.

## 4. `important_status_requester`

```text
Halo {requester_name}, tiket {ticket_number} telah diperbarui.
Status saat ini: {status}
Pantau perkembangan tiket: {tracking_url}
```

Variabel: `requester_name`, `ticket_number`, `status`, `tracking_url`.

Label status final yang saat ini diproduksi:

- `Perlu perbaikan` untuk aksi requester publik dengan outcome rejected.
- `Menunggu UAT` untuk `TicketUatAssigned`.
- `Sedang diproses` untuk `TicketAnalysisStarted`.
- `Menunggu informasi atau revisi dari pemohon` untuk `TicketRevisionRequested`.
- `UAT disetujui` untuk `TicketUatApproved`.
- `Ditolak` untuk `TicketRejected`.

## 5. `ticket_completed_requester`

```text
Halo {requester_name}, tiket {ticket_number} telah selesai.
Ringkasan penyelesaian: {resolution_summary}
Pantau tiket: {tracking_url}
Terima kasih.
```

Variabel: `requester_name`, `ticket_number`, `resolution_summary`, `tracking_url`.

## 6. `sla_recipient`

```text
Peringatan SLA tiket {ticket_number}
Jenis: {sla_type}
Status: {status}
Sisa/terlambat: {minutes} menit
Buka: {internal_url}
```

Variabel: `ticket_number`, `sla_type`, `status`, `minutes`, `internal_url`.

Penerima adalah current PIC bila nomornya valid. Jika tidak, penerima fallback adalah nomor IT Support yang dikonfigurasi. `sla_type` adalah `RESPONSE` atau `RESOLUTION`; status yang diproduksi adalah `Mendekati batas` atau `Terlewati`.

## 7. `test_it_support`

```text
Uji notifikasi WhatsApp APG CRM
Waktu: {time}
Catatan: {note}
```

Variabel: `time`, `note`. Pesan ini hanya dibuat dari command `crm:whatsapp-test` atau aksi admin **Kirim Pesan Uji**.

## Toggle event runtime

Key konfigurasi yang tersedia:

- `ticket_created`
- `ticket_assigned`
- `important_status_changed`
- `sla_warning`
- `sla_breached`
- `ticket_completed`

Penolakan aksi requester publik menggunakan `important_status_changed`. Toggle terpisah tanpa producer tidak diekspos.

Event `test` tidak memiliki toggle env khusus. Service tetap memeriksa global runtime enablement saat membuat pesan uji.

## Perubahan template

Template saat ini berasal dari config aplikasi, bukan database atau dashboard Fonnte. Perubahan wording harus dilakukan melalui perubahan kode/config ter-review, diikuti targeted tests dan pemeriksaan bahwa placeholder yang dipakai benar-benar disediakan oleh semua producer event. Jangan memasukkan token, secret, nomor lengkap, atau data produksi ke template source maupun test fixture dokumentasi.
