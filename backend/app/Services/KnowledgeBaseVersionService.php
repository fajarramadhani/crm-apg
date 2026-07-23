<?php

namespace App\Services;

use App\Models\KnowledgeBaseActivityLog;
use App\Models\KnowledgeBaseArticle;
use App\Models\KnowledgeBaseArticleVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class KnowledgeBaseVersionService
{
    public function createVersion(KnowledgeBaseArticle $article, User $creator, int $versionNumber, ?string $changeSummary = null): KnowledgeBaseArticleVersion
    {
        return KnowledgeBaseArticleVersion::create([
            'article_id' => $article->id,
            'version_number' => $versionNumber,
            'title' => $article->title,
            'summary' => $article->summary,
            'content' => $article->content,
            'visibility' => $article->visibility,
            'category_id' => $article->category_id,
            'application_id' => $article->application_id,
            'change_summary' => $changeSummary,
            'created_by' => $creator->id,
            'created_at' => now(),
        ]);
    }

    public function restoreVersion(KnowledgeBaseArticle $article, int $versionNumber, User $editor): KnowledgeBaseArticle
    {
        return DB::transaction(function () use ($article, $versionNumber, $editor) {
            $version = KnowledgeBaseArticleVersion::where('article_id', $article->id)
                ->where('version_number', $versionNumber)
                ->firstOrFail();

            // Increment version to create a new draft from the restored content
            $newVersion = $article->current_version + 1;

            $article->update([
                'title' => $version->title,
                'summary' => $version->summary,
                'content' => $version->content,
                'visibility' => $version->visibility,
                'category_id' => $version->category_id,
                'application_id' => $version->application_id,
                'status' => 'draft',
                'current_version' => $newVersion,
            ]);

            // Save the new version record
            $this->createVersion($article, $editor, $newVersion, "Restored from version {$versionNumber}");

            KnowledgeBaseActivityLog::create([
                'article_id' => $article->id,
                'actor_id' => $editor->id,
                'action' => 'version_restored',
                'metadata' => [
                    'restored_from_version' => $versionNumber,
                    'new_version' => $newVersion,
                ],
            ]);

            return $article;
        });
    }
}
