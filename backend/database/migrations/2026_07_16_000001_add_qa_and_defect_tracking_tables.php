<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('qa_assignee_id')->nullable()->after('current_assignee_id')->constrained('users')->nullOnDelete();
            $table->foreignId('qa_assigned_by')->nullable()->after('qa_assignee_id')->constrained('users')->nullOnDelete();
            $table->timestamp('qa_assigned_at')->nullable()->after('qa_assigned_by');
            $table->timestamp('qa_started_at')->nullable()->after('qa_assigned_at');
            $table->timestamp('qa_completed_at')->nullable()->after('qa_started_at');
            $table->unsignedInteger('qa_cycle_number')->default(0)->after('qa_completed_at');
            $table->string('latest_qa_result', 24)->nullable()->after('qa_cycle_number');
            $table->timestamp('ready_for_uat_at')->nullable()->after('latest_qa_result');
        });

        Schema::create('ticket_qa_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qa_user_id')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_qa_test_cases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->string('case_number', 50);
            $table->string('title', 200);
            $table->string('test_type', 32);
            $table->text('preconditions')->nullable();
            $table->json('steps');
            $table->text('expected_result');
            $table->string('priority', 20);
            $table->boolean('is_regression')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['ticket_id', 'case_number']);
        });

        Schema::create('ticket_qa_test_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qa_user_id')->constrained('users');
            $table->unsignedInteger('cycle_number');
            $table->unsignedInteger('run_number');
            $table->string('environment', 120);
            $table->string('build_reference', 255)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20); // in_progress, passed, failed, cancelled
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'run_number']);
        });

        Schema::create('ticket_qa_test_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('qa_test_run_id')->constrained('ticket_qa_test_runs')->cascadeOnDelete();
            $table->foreignId('qa_test_case_id')->constrained('ticket_qa_test_cases')->restrictOnDelete();
            $table->foreignId('executed_by')->constrained('users');
            $table->string('status', 20); // passed, failed, blocked, not_run
            $table->text('actual_result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();
            $table->unique(['qa_test_run_id', 'qa_test_case_id'], 'uq_run_case_result');
        });

        Schema::create('ticket_qa_defects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('qa_test_run_id')->constrained('ticket_qa_test_runs')->cascadeOnDelete();
            $table->foreignId('qa_test_case_id')->nullable()->constrained('ticket_qa_test_cases')->nullOnDelete();
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->constrained('users');
            $table->string('defect_number', 50);
            $table->string('title', 200);
            $table->text('description');
            $table->string('severity', 20); // critical, major, minor, cosmetic
            $table->string('priority', 20); // urgent, high, medium, low
            $table->text('steps_to_reproduce');
            $table->text('expected_result');
            $table->text('actual_result');
            $table->string('environment', 120)->nullable();
            $table->string('status', 20); // open, in_progress, resolved, retest, verified, reopened, rejected
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'defect_number']);
        });

        Schema::create('ticket_qa_defect_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('defect_id')->constrained('ticket_qa_defects')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('action', 40);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('actor_role', 40);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ticket_qa_defect_sequences', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->primary()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('ticket_attachments', function (Blueprint $table): void {
            $table->foreignId('defect_id')->nullable()->constrained('ticket_qa_defects')->nullOnDelete();
        });

        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->foreignId('defect_id')->nullable()->constrained('ticket_qa_defects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->dropForeign(['defect_id']);
            $table->dropColumn('defect_id');
        });

        Schema::table('ticket_attachments', function (Blueprint $table): void {
            $table->dropForeign(['defect_id']);
            $table->dropColumn('defect_id');
        });

        Schema::dropIfExists('ticket_qa_defect_sequences');
        Schema::dropIfExists('ticket_qa_defect_histories');
        Schema::dropIfExists('ticket_qa_defects');
        Schema::dropIfExists('ticket_qa_test_results');
        Schema::dropIfExists('ticket_qa_test_runs');
        Schema::dropIfExists('ticket_qa_test_cases');
        Schema::dropIfExists('ticket_qa_assignments');

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['qa_assignee_id']);
            $table->dropForeign(['qa_assigned_by']);
            $table->dropColumn([
                'qa_assignee_id',
                'qa_assigned_by',
                'qa_assigned_at',
                'qa_started_at',
                'qa_completed_at',
                'qa_cycle_number',
                'latest_qa_result',
                'ready_for_uat_at',
            ]);
        });
    }
};
