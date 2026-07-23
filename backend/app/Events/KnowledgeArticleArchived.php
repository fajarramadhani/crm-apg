<?php

namespace App\Events;

use App\Models\KnowledgeBaseArticle;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

class KnowledgeArticleArchived
{
    use Dispatchable;

    public function __construct(public KnowledgeBaseArticle $article, public User $actor) {}
}
