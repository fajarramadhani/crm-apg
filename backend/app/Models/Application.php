<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'description', 'owner_division_id', 'is_active', 'system_type', 'contact_person', 'vendor', 'tags'])]
class Application extends Model
{
    use HasActiveScope;

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'tags' => 'array'];
    }

    public function ownerDivision(): BelongsTo
    {
        return $this->belongsTo(Division::class, 'owner_division_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(ApplicationModule::class);
    }
}
