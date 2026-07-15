<?php

namespace App\Models;

use App\Models\Concerns\HasActiveScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'type', 'description', 'is_active'])]
class TicketCategory extends Model
{
    use HasActiveScope;

    public const TYPES = ['incident', 'request', 'change', 'problem'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
