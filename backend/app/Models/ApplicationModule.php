<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['application_id', 'code', 'name', 'description', 'is_active'])]
class ApplicationModule extends Model
{
    use HasActiveScope;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
