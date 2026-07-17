<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('uat_assignee_id')->nullable()->constrained('users');
            $table->foreignId('uat_assigned_by')->nullable()->constrained('users');
            $table->timestamp('uat_assigned_at')->nullable();
            $table->timestamp('uat_started_at')->nullable();
            $table->timestamp('uat_completed_at')->nullable();
            $table->integer('uat_cycle_number')->default(0);
            $table->string('latest_uat_result')->nullable();
            $table->timestamp('uat_approved_at')->nullable();
        });

        Schema::create('ticket_uat_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_uat_scenarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users');
            $table->string('scenario_number');
            $table->string('title');
            $table->text('business_objective');
            $table->text('preconditions')->nullable();
            $table->json('steps');
            $table->text('expected_result');
            $table->json('acceptance_criteria');
            $table->string('priority');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['ticket_id', 'scenario_number']);
        });

        Schema::create('ticket_uat_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('requester_id')->constrained('users')->onDelete('cascade');
            $table->integer('cycle_number');
            $table->integer('run_number');
            $table->string('environment');
            $table->string('build_reference')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status');
            $table->text('summary')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_uat_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uat_run_id')->constrained('ticket_uat_runs')->onDelete('cascade');
            $table->foreignId('uat_scenario_id')->constrained('ticket_uat_scenarios')->onDelete('cascade');
            $table->foreignId('executed_by')->constrained('users');
            $table->string('status');
            $table->text('actual_result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->unique(['uat_run_id', 'uat_scenario_id']);
        });

        Schema::create('ticket_uat_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->foreignId('uat_run_id')->constrained('ticket_uat_runs')->onDelete('cascade');
            $table->foreignId('uat_scenario_id')->nullable()->constrained('ticket_uat_scenarios')->onDelete('set null');
            $table->foreignId('reported_by')->constrained('users');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->string('finding_number')->unique();
            $table->string('title');
            $table->text('description');
            $table->text('business_impact');
            $table->string('severity');
            $table->string('status');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_uat_finding_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('uat_finding_id')->constrained('ticket_uat_findings')->onDelete('cascade');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('action');
            $table->foreignId('actor_id')->constrained('users');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('ticket_uat_finding_sequences', function (Blueprint $table) {
            $table->foreignId('ticket_id')->primary()->constrained('tickets')->onDelete('cascade');
            $table->integer('last_number')->default(0);
            $table->timestamps();
        });

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->foreignId('uat_finding_id')->nullable()->constrained('ticket_uat_findings')->onDelete('cascade');
        });

        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->foreignId('uat_finding_id')->nullable()->constrained('ticket_uat_findings')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_comments', function (Blueprint $table) {
            $table->dropForeign(['uat_finding_id']);
            $table->dropColumn('uat_finding_id');
        });

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->dropForeign(['uat_finding_id']);
            $table->dropColumn('uat_finding_id');
        });

        Schema::dropIfExists('ticket_uat_finding_sequences');
        Schema::dropIfExists('ticket_uat_finding_histories');
        Schema::dropIfExists('ticket_uat_findings');
        Schema::dropIfExists('ticket_uat_results');
        Schema::dropIfExists('ticket_uat_runs');
        Schema::dropIfExists('ticket_uat_scenarios');
        Schema::dropIfExists('ticket_uat_assignments');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['uat_assignee_id']);
            $table->dropForeign(['uat_assigned_by']);
            $table->dropColumn([
                'uat_assignee_id',
                'uat_assigned_by',
                'uat_assigned_at',
                'uat_started_at',
                'uat_completed_at',
                'uat_cycle_number',
                'latest_uat_result',
                'uat_approved_at',
            ]);
        });
    }
};
