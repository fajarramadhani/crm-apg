<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowApprovalStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'approval_config_id',
        'step_order',
        'approver_role_key',
    ];

    protected $casts = [
        'step_order' => 'integer',
    ];

    public function approvalConfig(): BelongsTo
    {
        return $this->belongsTo(WorkflowApprovalConfig::class, 'approval_config_id');
    }
}
