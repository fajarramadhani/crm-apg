<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WhatsAppNotificationSetting extends Model
{
    protected $table = 'whatsapp_notification_settings';

    protected $fillable = ['scope', 'enabled', 'events'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'events' => 'array'];
    }
}
