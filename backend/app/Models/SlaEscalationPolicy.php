<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaEscalationPolicy extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'escalate_to_it_lead' => 'boolean',
        'escalate_to_manager' => 'boolean',
        'escalate_to_supervisor' => 'boolean',
        'warning_threshold_percent' => 'integer',
        'critical_threshold_percent' => 'integer',
        'inactivity_threshold_minutes' => 'integer',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
