<?php

namespace App\Listeners;

use App\Events\KnowledgeArticleArchived;
use App\Events\KnowledgeArticlePublished;
use App\Events\KnowledgeArticleRejected;
use App\Events\KnowledgeArticleSubmitted;
use App\Services\KnowledgeBaseNotificationService;
use Illuminate\Events\Dispatcher;

class KnowledgeBaseNotificationSubscriber
{
    public function __construct(private KnowledgeBaseNotificationService $notificationService) {}

    public function handleSubmitted(KnowledgeArticleSubmitted $event): void
    {
        $this->notificationService->dispatch(
            article: $event->article,
            type: 'knowledge_article_submitted',
            severity: 'info',
            title: 'Knowledge Article Submitted',
            message: "Article {$event->article->article_number} has been submitted for review by {$event->actor->name}.",
            actionUrl: "/knowledge-base/{$event->article->slug}",
            actorId: $event->actor->id
        );
    }

    public function handlePublished(KnowledgeArticlePublished $event): void
    {
        $this->notificationService->dispatch(
            article: $event->article,
            type: 'knowledge_article_published',
            severity: 'success',
            title: 'Knowledge Article Published',
            message: "Your article {$event->article->article_number} has been published successfully.",
            actionUrl: "/knowledge-base/{$event->article->slug}",
            actorId: $event->actor->id
        );
    }

    public function handleRejected(KnowledgeArticleRejected $event): void
    {
        $this->notificationService->dispatch(
            article: $event->article,
            type: 'knowledge_article_rejected',
            severity: 'warning',
            title: 'Knowledge Article Rejected',
            message: "Your article {$event->article->article_number} has been rejected. Reason: {$event->article->rejection_reason}",
            actionUrl: "/knowledge-base/{$event->article->slug}",
            actorId: $event->actor->id
        );
    }

    public function handleArchived(KnowledgeArticleArchived $event): void
    {
        $this->notificationService->dispatch(
            article: $event->article,
            type: 'knowledge_article_archived',
            severity: 'info',
            title: 'Knowledge Article Archived',
            message: "Article {$event->article->article_number} has been archived.",
            actionUrl: "/knowledge-base/{$event->article->slug}",
            actorId: $event->actor->id
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            KnowledgeArticleSubmitted::class => 'handleSubmitted',
            KnowledgeArticlePublished::class => 'handlePublished',
            KnowledgeArticleRejected::class => 'handleRejected',
            KnowledgeArticleArchived::class => 'handleArchived',
        ];
    }
}
