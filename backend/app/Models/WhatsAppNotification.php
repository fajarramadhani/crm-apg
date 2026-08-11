<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppNotification extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_notifications';

    protected $fillable = [
        'event_type',
        'ticket_id',
        'recipient_hash',
        'recipient_last_four',
        'recipient_encrypted',
        'recipient_role',
        'recipient_type',
        'template_name',
        'rendered_message',
        'deduplication_key',
        'provider',
        'provider_message_id',
        'provider_message_ids',
        'provider_request_id',
        'provider_response',
        'status',
        'attempts',
        'queued_at',
        'processing_at',
        'sent_at',
        'failed_at',
        'expired_at',
        'failure_code',
        'failure_message_safe',
    ];

    protected function casts(): array
    {
        return [
            'provider_message_ids' => 'array',
            'provider_response' => 'array',
            'attempts' => 'integer',
            'recipient_encrypted' => 'encrypted',
            'queued_at' => 'datetime',
            'processing_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'expired_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
