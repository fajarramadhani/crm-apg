<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['article_number', 'slug', 'title', 'summary', 'content', 'status', 'visibility', 'category_id', 'application_id', 'author_id', 'reviewer_id', 'published_by', 'published_at', 'rejected_by', 'rejected_at', 'rejection_reason', 'archived_by', 'archived_at', 'current_version', 'view_count', 'helpful_count', 'not_helpful_count'])]
class KnowledgeBaseArticle extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(KnowledgeBaseArticleVersion::class, 'article_id')->orderBy('version_number', 'desc');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBaseTag::class, 'knowledge_base_article_tag', 'article_id', 'tag_id');
    }

    public function tickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'knowledge_base_article_ticket', 'article_id', 'ticket_id')
            ->withPivot(['relation_type', 'linked_by', 'created_at']);
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(KnowledgeBaseFeedback::class, 'article_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(KnowledgeBaseActivityLog::class, 'article_id')->latest();
    }
}
