<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['key', 'name', 'level', 'description', 'is_active'])]
class TicketPriority extends Model
{
    use HasActiveScope;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'level' => 'integer'];
    }

    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class, 'priority_id');
    }

    public function slaPolicy(): HasOne
    {
        return $this->hasOne(SlaPolicy::class, 'priority_id')->where('is_active', true);
    }
}
