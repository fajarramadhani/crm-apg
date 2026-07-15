<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_number_sequences', function (Blueprint $table): void {
            $table->string('period', 6)->primary();
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table): void {
            $table->id();
            $table->string('ticket_number', 24)->unique();
            $table->foreignId('requester_id')->constrained('users');
            $table->foreignId('division_id')->constrained('divisions');
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('application_module_id')->nullable()->constrained('application_modules')->nullOnDelete();
            $table->foreignId('ticket_category_id')->constrained('ticket_categories');
            $table->foreignId('requested_priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->foreignId('final_priority_id')->nullable()->constrained('ticket_priorities')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->text('business_impact')->nullable();
            $table->string('urgency', 30)->nullable();
            $table->timestamp('incident_occurred_at')->nullable();
            $table->string('affected_url', 2048)->nullable();
            $table->text('expected_result')->nullable();
            $table->text('actual_result')->nullable();
            $table->text('reproduction_steps')->nullable();
            $table->text('request_purpose')->nullable();
            $table->timestamp('target_needed_at')->nullable();
            $table->text('change_reason')->nullable();
            $table->text('expected_impact')->nullable();
            $table->text('recurring_indication')->nullable();
            $table->string('status', 32)->index();
            $table->foreignId('current_division_id')->constrained('divisions');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['requester_id', 'created_at']);
            $table->index(['current_division_id', 'status', 'submitted_at']);
        });

        Schema::create('ticket_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('action', 40);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('actor_role', 40);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('ticket_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('type', 40);
            $table->text('comment');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk', 40);
            $table->string('path');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->string('category', 30)->default('other');
            $table->timestamps();
            $table->index(['ticket_id', 'uploaded_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_comments');
        Schema::dropIfExists('ticket_status_histories');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_number_sequences');
    }
};
