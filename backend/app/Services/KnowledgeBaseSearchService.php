<?php

namespace App\Services;

use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class KnowledgeBaseSearchService
{
    public function search(User $user, array $filters): LengthAwarePaginator
    {
        $query = KnowledgeBaseArticle::query()
            ->with(['author', 'category', 'application', 'tags']);

        // 1. Enforce visibility constraint
        $this->applyVisibilityConstraints($query, $user);

        // 2. Status constraint
        if (isset($filters['status']) && ($user->hasPermission('knowledge_base.review') || $user->hasPermission('knowledge_base.edit_any'))) {
            $query->where('status', $filters['status']);
        } elseif (isset($filters['status']) && $user->hasPermission('knowledge_base.create')) {
            $query->where('status', $filters['status'])->where('author_id', $user->id);
        } else {
            // Normal users only see published
            $query->where('status', 'published');
        }

        // 3. Search query
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('article_number', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhereHas('tags', function ($tagQuery) use ($search) {
                        $tagQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('category', function ($catQuery) use ($search) {
                        $catQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('application', function ($appQuery) use ($search) {
                        $appQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // 4. Other filters
        if (isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }
        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (isset($filters['application_id'])) {
            $query->where('application_id', $filters['application_id']);
        }
        if (isset($filters['author_id'])) {
            $query->where('author_id', $filters['author_id']);
        }
        if (isset($filters['tag'])) {
            $tag = $filters['tag'];
            $query->whereHas('tags', function ($tagQuery) use ($tag) {
                if (is_numeric($tag)) {
                    $tagQuery->where('knowledge_base_tags.id', $tag);
                } else {
                    $tagQuery->where('knowledge_base_tags.name', $tag)
                        ->orWhere('knowledge_base_tags.slug', $tag);
                }
            });
        }

        if (isset($filters['published_from'])) {
            $query->whereDate('published_at', '>=', Carbon::parse($filters['published_from']));
        }
        if (isset($filters['published_to'])) {
            $query->whereDate('published_at', '<=', Carbon::parse($filters['published_to']));
        }

        // 5. Sorting (Allow-list)
        $sortBy = $filters['sort_by'] ?? 'published_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['published_at', 'created_at', 'updated_at', 'title', 'view_count', 'helpful_count'];

        if (! in_array($sortBy, $allowedSorts, true)) {
            abort(422, 'Invalid sort parameter.');
        }

        $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');

        // 6. Pagination
        $perPage = min($filters['per_page'] ?? 20, 100);

        return $query->paginate($perPage);
    }

    public function applyVisibilityConstraints(Builder $query, User $user): void
    {
        // IT Lead, PIC, QA and Admin with view_it_internal permission can view all internal articles
        if ($user->hasPermission('knowledge_base.view_it_internal')) {
            $query->whereIn('visibility', ['it_internal', 'business_internal', 'all_authenticated']);
        } else {
            // Business roles can only view business_internal and all_authenticated
            $query->whereIn('visibility', ['business_internal', 'all_authenticated']);
        }
    }
}
