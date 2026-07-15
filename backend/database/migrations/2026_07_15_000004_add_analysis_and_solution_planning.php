<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyst_id')->constrained('users');
            $table->unsignedInteger('version');
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->text('problem_summary');
            $table->text('root_cause')->nullable();
            $table->text('technical_impact');
            $table->text('business_impact')->nullable();
            $table->json('affected_components')->nullable();
            $table->text('evidence')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('limitations')->nullable();
            $table->timestamp('analysis_started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'version']);
            $table->index(['ticket_id', 'is_current']);
        });

        Schema::create('ticket_solution_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->unsignedInteger('version');
            $table->unsignedInteger('lock_version')->default(1);
            $table->boolean('is_current')->default(true);
            $table->text('solution_summary');
            $table->json('implementation_steps');
            $table->json('affected_components')->nullable();
            $table->json('dependencies')->nullable();
            $table->unsignedInteger('estimated_effort_minutes');
            $table->string('risk_level', 16);
            $table->text('risk_description')->nullable();
            $table->text('rollback_plan')->nullable();
            $table->text('testing_plan');
            $table->text('deployment_consideration')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestamps();
            $table->unique(['ticket_id', 'version']);
            $table->index(['ticket_id', 'is_current']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('analysis_started_at')->nullable()->after('assigned_at');
            $table->timestamp('analysis_completed_at')->nullable()->after('analysis_started_at');
            $table->timestamp('plan_submitted_at')->nullable()->after('analysis_completed_at');
            $table->timestamp('plan_approved_at')->nullable()->after('plan_submitted_at');
            $table->foreignId('current_analysis_id')->nullable()->after('plan_approved_at')->constrained('ticket_analyses')->nullOnDelete();
            $table->foreignId('current_solution_plan_id')->nullable()->after('current_analysis_id')->constrained('ticket_solution_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_solution_plan_id');
            $table->dropConstrainedForeignId('current_analysis_id');
            $table->dropColumn(['analysis_started_at', 'analysis_completed_at', 'plan_submitted_at', 'plan_approved_at']);
        });
        Schema::dropIfExists('ticket_solution_plans');
        Schema::dropIfExists('ticket_analyses');
    }
};
