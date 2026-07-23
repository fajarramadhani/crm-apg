<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_base_number_sequences', function (Blueprint $table) {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('knowledge_base_articles', function (Blueprint $table) {
            $table->id();
            $table->string('article_number')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary');
            $table->longText('content');
            $table->string('status')->default('draft')->index();
            $table->string('visibility')->default('all_authenticated')->index();
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedInteger('view_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('not_helpful_count')->default(0);
            $table->timestamps();
        });

        Schema::create('knowledge_base_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title');
            $table->text('summary');
            $table->longText('content');
            $table->string('visibility');
            $table->foreignId('category_id')->nullable()->constrained('ticket_categories')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'version_number']);
        });

        Schema::create('knowledge_base_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('knowledge_base_article_tag', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained('knowledge_base_tags')->cascadeOnDelete();
            $table->primary(['article_id', 'tag_id']);
        });

        Schema::create('knowledge_base_article_ticket', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('relation_type')->index(); // source, related, used_as_solution, recommended
            $table->foreignId('linked_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'ticket_id', 'relation_type']);
        });

        Schema::create('knowledge_base_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_helpful');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'user_id']);
        });

        Schema::create('knowledge_base_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_base_articles')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_base_activity_logs');
        Schema::dropIfExists('knowledge_base_feedback');
        Schema::dropIfExists('knowledge_base_article_ticket');
        Schema::dropIfExists('knowledge_base_article_tag');
        Schema::dropIfExists('knowledge_base_tags');
        Schema::dropIfExists('knowledge_base_article_versions');
        Schema::dropIfExists('knowledge_base_articles');
        Schema::dropIfExists('knowledge_base_number_sequences');
    }
};
