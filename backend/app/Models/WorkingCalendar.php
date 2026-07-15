<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'timezone', 'workday_start', 'workday_end', 'working_days', 'is_active'])]
class WorkingCalendar extends Model
{
    use HasActiveScope;

    public const VALID_WORKING_DAYS = [1, 2, 3, 4, 5, 6, 7];

    protected function casts(): array
    {
        return ['working_days' => 'array', 'is_active' => 'boolean'];
    }

    public function holidays(): HasMany
    {
        return $this->hasMany(Holiday::class);
    }

    public function slaPolicies(): HasMany
    {
        return $this->hasMany(SlaPolicy::class);
    }
}
