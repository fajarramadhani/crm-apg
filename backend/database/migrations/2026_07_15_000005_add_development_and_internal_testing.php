<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('development_started_at')->nullable()->after('plan_approved_at');
            $table->timestamp('development_completed_at')->nullable();
            $table->timestamp('internal_testing_started_at')->nullable();
            $table->timestamp('internal_testing_completed_at')->nullable();
            $table->timestamp('ready_for_qa_at')->nullable();
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->timestamp('latest_progress_at')->nullable();
        });
        Schema::table('ticket_attachments', fn (Blueprint $table) => $table->string('visibility', 20)->default('internal')->after('category'));
        Schema::create('ticket_worklogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('work_date');
            $table->unsignedInteger('minutes_spent');
            $table->string('activity_type', 32);
            $table->text('description');
            $table->unsignedTinyInteger('progress_before');
            $table->unsignedTinyInteger('progress_after');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->index(['ticket_id', 'work_date']);
        });
        Schema::create('ticket_development_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedTinyInteger('progress_percentage');
            $table->text('summary');
            $table->json('completed_items')->nullable();
            $table->json('remaining_items')->nullable();
            $table->json('blockers')->nullable();
            $table->json('next_steps')->nullable();
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
            $table->index(['ticket_id', 'created_at']);
        });
        Schema::create('ticket_internal_test_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('case_number', 50);
            $table->string('title', 200);
            $table->text('preconditions')->nullable();
            $table->json('steps');
            $table->text('expected_result');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['ticket_id', 'case_number']);
        });
        Schema::create('ticket_internal_test_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('executed_by')->constrained('users');
            $table->unsignedInteger('run_number');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20);
            $table->string('environment', 120);
            $table->string('build_reference', 255)->nullable();
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'run_number']);
            $table->index(['ticket_id', 'status']);
        });
        Schema::create('ticket_internal_test_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('test_run_id')->constrained('ticket_internal_test_runs')->cascadeOnDelete();
            $table->foreignId('test_case_id')->constrained('ticket_internal_test_cases')->restrictOnDelete();
            $table->foreignId('executed_by')->constrained('users');
            $table->string('status', 20);
            $table->text('actual_result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
            $table->unique(['test_run_id', 'test_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_internal_test_results');
        Schema::dropIfExists('ticket_internal_test_runs');
        Schema::dropIfExists('ticket_internal_test_cases');
        Schema::dropIfExists('ticket_development_updates');
        Schema::dropIfExists('ticket_worklogs');
        Schema::table('ticket_attachments', fn (Blueprint $table) => $table->dropColumn('visibility'));
        Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn(['development_started_at', 'development_completed_at', 'internal_testing_started_at', 'internal_testing_completed_at', 'ready_for_qa_at', 'progress_percentage', 'latest_progress_at']));
    }
};
