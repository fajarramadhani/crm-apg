<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowCondition extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_id',
        'name',
        'field',
        'operator',
        'value',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(WorkflowDefinition::class, 'workflow_id');
    }
}
