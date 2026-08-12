<?php

return [
    'enabled' => (bool) env('WHATSAPP_NOTIFICATION_ENABLED', false),
    'provider' => env('WHATSAPP_PROVIDER', 'fonnte'),
    'queue' => 'notifications',
    'queue_tries' => 2,
    'fonnte' => [
        'base_url' => rtrim((string) env('FONNTE_BASE_URL', 'https://api.fonnte.com'), '/'),
        'token' => env('FONNTE_TOKEN', ''),
        'country_code' => env('FONNTE_COUNTRY_CODE', '62'),
        'connect_only' => filter_var(env('FONNTE_CONNECT_ONLY', true), FILTER_VALIDATE_BOOL),
        'timeout' => (int) env('FONNTE_TIMEOUT', 15),
        'retry_times' => max(1, (int) env('FONNTE_RETRY_TIMES', 1)),
        'it_support_number' => env('FONNTE_IT_SUPPORT_NUMBER', ''),
        'webhook_secret' => env('FONNTE_WEBHOOK_SECRET', ''),
    ],
    'retry_backoff_seconds' => [60, 300, 900],
    'events' => [
        'ticket_created' => (bool) env('WHATSAPP_EVENT_TICKET_CREATED', true),
        'ticket_assigned' => (bool) env('WHATSAPP_EVENT_TICKET_ASSIGNED', true),
        'important_status_changed' => (bool) env('WHATSAPP_EVENT_IMPORTANT_STATUS_CHANGED', true),
        'sla_warning' => (bool) env('WHATSAPP_EVENT_SLA_WARNING', true),
        'sla_breached' => (bool) env('WHATSAPP_EVENT_SLA_BREACHED', true),
        'ticket_completed' => (bool) env('WHATSAPP_EVENT_TICKET_COMPLETED', true),
    ],
    'recipients' => [
        'ticket_created_it_support' => (bool) env('WHATSAPP_RECIPIENT_TICKET_CREATED_IT_SUPPORT', false),
    ],
    'templates' => [
        'ticket_created_requester' => "Halo {requester_name},\n\nPengajuan Anda berhasil diterima.\n\nNomor Tiket: {ticket_number}\nKategori: {category}\nStatus: {status}\n\nPantau perkembangan tiket melalui:\n{tracking_url}\n\nPesan ini dikirim otomatis oleh APG CRM.",
        'ticket_created_it_support' => "Tiket baru {ticket_number}: {title}\nPemohon: {requester_name}\nCabang: {branch}\nBuka: {internal_url}",
        'ticket_assigned_pic' => "Halo {pic_name}, tiket {ticket_number} ditugaskan kepada Anda.\nJudul: {title}\nPrioritas: {priority}\nPemohon: {requester_name}\nCabang: {branch}\nBuka tiket: {internal_url}",
        'important_status_requester' => "Halo {requester_name}, tiket {ticket_number} telah diperbarui.\nStatus saat ini: {status}\nPantau perkembangan tiket: {tracking_url}",
        'ticket_completed_requester' => "Halo {requester_name}, tiket {ticket_number} telah selesai.\nRingkasan penyelesaian: {resolution_summary}\nPantau tiket: {tracking_url}\nTerima kasih.",
        'sla_recipient' => "Peringatan SLA tiket {ticket_number}\nJenis: {sla_type}\nStatus: {status}\nSisa/terlambat: {minutes} menit\nBuka: {internal_url}",
        'test_it_support' => "Uji notifikasi WhatsApp APG CRM\nWaktu: {time}\nCatatan: {note}",
    ],
];
