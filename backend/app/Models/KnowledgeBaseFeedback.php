<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['article_id', 'user_id', 'is_helpful', 'comment'])]
class KnowledgeBaseFeedback extends Model
{
    protected $table = 'knowledge_base_feedback';

    protected function casts(): array
    {
        return [
            'is_helpful' => 'boolean',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBaseArticle::class, 'article_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
