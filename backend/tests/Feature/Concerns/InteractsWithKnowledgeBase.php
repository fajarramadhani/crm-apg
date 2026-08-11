<?php

namespace Tests\Feature\Concerns;

use App\Models\KnowledgeBaseArticle;
use App\Models\Role;
use App\Models\User;
use App\Services\KnowledgeBaseArticleService;
use Database\Seeders\MasterDataSeeder;
use Database\Seeders\RoleSeeder;

trait InteractsWithKnowledgeBase
{
    protected array $kbUsers = [];

    protected function setUpKnowledgeBase(): void
    {
        $this->seed([RoleSeeder::class, MasterDataSeeder::class]);

        foreach (['requester', 'pic', 'manager', 'executive', 'it_lead', 'superadmin'] as $role) {
            $this->kbUsers[$role] = User::factory()->create([
                'role_id' => Role::where('key', $role)->firstOrFail()->id,
                'name' => ucfirst(str_replace('_', ' ', $role)),
                'email' => $role.'@kb.test',
            ]);
        }

        $this->kbUsers['other_pic'] = User::factory()->create([
            'role_id' => Role::where('key', 'pic')->firstOrFail()->id,
            'name' => 'Other PIC',
            'email' => 'other-pic@kb.test',
        ]);
    }

    protected function kbUser(string $role): User
    {
        return $this->kbUsers[$role];
    }

    protected function createKbArticle(array $attributes = [], ?User $author = null): KnowledgeBaseArticle
    {
        $article = app(KnowledgeBaseArticleService::class)->create($author ?? $this->kbUser('pic'), array_merge([
            'title' => 'Reset Corporate Password',
            'summary' => 'Steps for resetting a corporate account password.',
            'content' => '<p>Open the account portal and select Reset Password.</p>',
            'visibility' => 'all_authenticated',
        ], $attributes));

        if (array_key_exists('status', $attributes)) {
            $status = $attributes['status'];
            $article->forceFill([
                'status' => $status,
                'published_at' => $status === 'published' ? ($attributes['published_at'] ?? now()) : null,
            ])->save();
        }

        return $article->fresh();
    }

    protected function articlePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Configure Secure VPN',
            'summary' => 'A complete guide to configuring secure remote access.',
            'content' => '<p>Install the approved VPN client and sign in.</p>',
            'visibility' => 'all_authenticated',
        ], $overrides);
    }
}
