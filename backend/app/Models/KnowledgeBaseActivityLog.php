<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['article_id', 'actor_id', 'action', 'from_status', 'to_status', 'metadata'])]
class KnowledgeBaseActivityLog extends Model
{
    protected $table = 'knowledge_base_activity_logs';

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'article_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
