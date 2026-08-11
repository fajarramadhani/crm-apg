<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStageField extends Model
{
    use HasFactory;

    protected $fillable = [
        'stage_id',
        'field_name',
        'is_required',
        'is_readonly',
        'is_hidden',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_readonly' => 'boolean',
        'is_hidden' => 'boolean',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(WorkflowStage::class, 'stage_id');
    }
}
