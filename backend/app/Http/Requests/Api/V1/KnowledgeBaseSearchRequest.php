<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\KnowledgeBaseArticleStatus;
use App\Enums\KnowledgeBaseVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeBaseSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:200'],
            'status' => ['nullable', Rule::enum(KnowledgeBaseArticleStatus::class)],
            'visibility' => ['nullable', Rule::enum(KnowledgeBaseVisibility::class)],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'application_id' => ['nullable', 'integer', 'exists:applications,id'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'tag' => ['nullable', 'string', 'max:100'],
            'published_from' => ['nullable', 'date'],
            'published_to' => ['nullable', 'date', 'after_or_equal:published_from'],
            'sort_by' => ['nullable', Rule::in(['published_at', 'title', 'article_number', 'helpful_ratio', 'created_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
