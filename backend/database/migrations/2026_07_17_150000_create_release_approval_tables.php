<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('approval_requested_at')->nullable();
            $table->timestamp('approval_completed_at')->nullable();
            $table->timestamp('approved_for_release_at')->nullable();
            $table->timestamp('release_preparation_started_at')->nullable();
            $table->timestamp('release_ready_at')->nullable();
            $table->string('release_risk_level', 20)->nullable();
            $table->foreignId('release_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('latest_approval_result', 20)->nullable();
            $table->unsignedInteger('approval_cycle_number')->default(0);
            $table->index(['status', 'approval_requested_at']);
        });

        Schema::create('ticket_approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->foreignId('requested_by')->constrained('users');
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->index();
            $table->text('summary');
            $table->text('business_impact')->nullable();
            $table->string('release_risk_level', 20);
            $table->timestamp('proposed_release_at')->nullable();
            $table->text('revision_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['ticket_id', 'cycle_number']);
        });

        Schema::create('ticket_approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('ticket_approval_requests')->cascadeOnDelete();
            $table->string('step_type', 30);
            $table->unsignedInteger('sequence');
            $table->foreignId('approver_id')->constrained('users');
            $table->string('status', 20)->index();
            $table->timestamp('assigned_at');
            $table->timestamp('acted_at')->nullable();
            $table->text('decision_notes')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['approval_request_id', 'step_type']);
        });

        Schema::create('ticket_approval_action_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_request_id')->constrained('ticket_approval_requests')->cascadeOnDelete();
            $table->foreignId('approval_step_id')->nullable()->constrained('ticket_approval_steps')->nullOnDelete();
            $table->string('action', 50);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['approval_request_id', 'created_at'], 'approval_histories_request_created_idx');
        });

        Schema::create('ticket_release_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_request_id')->constrained('ticket_approval_requests')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('release_owner_id')->constrained('users');
            $table->string('release_type', 20);
            $table->string('target_environment', 20);
            $table->text('change_summary');
            $table->text('technical_summary');
            $table->json('affected_components');
            $table->json('dependencies')->nullable();
            $table->text('database_changes')->nullable();
            $table->boolean('data_migration_required')->default(false);
            $table->boolean('downtime_required')->default(false);
            $table->unsignedInteger('estimated_downtime_minutes')->nullable();
            $table->timestamp('proposed_start_at')->nullable();
            $table->unsignedInteger('estimated_duration_minutes');
            $table->json('validation_steps');
            $table->json('monitoring_plan');
            $table->text('communication_notes')->nullable();
            $table->string('status', 20)->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['ticket_id', 'version']);
        });

        Schema::create('ticket_rollback_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_plan_id')->constrained('ticket_release_plans')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('created_by')->constrained('users');
            $table->text('rollback_trigger');
            $table->json('rollback_steps');
            $table->json('data_recovery_steps')->nullable();
            $table->unsignedInteger('estimated_rollback_minutes');
            $table->json('validation_after_rollback');
            $table->foreignId('responsible_user_id')->constrained('users');
            $table->string('status', 20)->index();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamps();
            $table->unique(['ticket_id', 'version']);
        });

        Schema::create('release_checklist_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->boolean('is_required')->default(true);
            $table->string('applies_to_release_type', 20)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('ticket_release_checklist_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_plan_id')->constrained('ticket_release_plans')->cascadeOnDelete();
            $table->foreignId('template_id')->nullable()->constrained('release_checklist_templates')->nullOnDelete();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('category', 30);
            $table->boolean('is_required')->default(true);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evidence_attachment_id')->nullable()->constrained('ticket_attachments')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('ticket_release_checklist_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('checklist_item_id')->constrained('ticket_release_checklist_items')->cascadeOnDelete();
            $table->string('action', 30);
            $table->foreignId('actor_id')->constrained('users');
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('notes')->nullable();
            $table->timestamp('created_at');
        });

        DB::table('release_checklist_templates')->insert([
            ['code' => 'qa_passed', 'label' => 'QA passed', 'category' => 'business', 'is_required' => true, 'sort_order' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'uat_approved', 'label' => 'UAT approved', 'category' => 'business', 'is_required' => true, 'sort_order' => 20, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'business_approval', 'label' => 'Business approval completed', 'category' => 'business', 'is_required' => true, 'sort_order' => 30, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'technical_readiness', 'label' => 'Technical readiness approved', 'category' => 'code', 'is_required' => true, 'sort_order' => 40, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'release_plan', 'label' => 'Release plan completed', 'category' => 'documentation', 'is_required' => true, 'sort_order' => 50, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'rollback_plan', 'label' => 'Rollback plan completed', 'category' => 'rollback', 'is_required' => true, 'sort_order' => 60, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'backup_plan', 'label' => 'Database backup plan available', 'category' => 'backup', 'is_required' => true, 'sort_order' => 70, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'migration_reviewed', 'label' => 'Migration command reviewed', 'category' => 'database', 'is_required' => true, 'sort_order' => 80, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'environment_reviewed', 'label' => 'Environment variables reviewed', 'category' => 'security', 'is_required' => true, 'sort_order' => 90, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'security_reviewed', 'label' => 'Security impact reviewed', 'category' => 'security', 'is_required' => true, 'sort_order' => 100, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'monitoring_prepared', 'label' => 'Monitoring steps prepared', 'category' => 'monitoring', 'is_required' => true, 'sort_order' => 110, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'communication_prepared', 'label' => 'Stakeholder communication prepared', 'category' => 'communication', 'is_required' => true, 'sort_order' => 120, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'release_owner', 'label' => 'Release owner assigned', 'category' => 'business', 'is_required' => true, 'sort_order' => 130, 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'post_release_validation', 'label' => 'Post-release validation prepared', 'category' => 'monitoring', 'is_required' => true, 'sort_order' => 140, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_release_checklist_histories');
        Schema::dropIfExists('ticket_release_checklist_items');
        Schema::dropIfExists('release_checklist_templates');
        Schema::dropIfExists('ticket_rollback_plans');
        Schema::dropIfExists('ticket_release_plans');
        Schema::dropIfExists('ticket_approval_action_histories');
        Schema::dropIfExists('ticket_approval_steps');
        Schema::dropIfExists('ticket_approval_requests');
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropForeign(['release_owner_id']);
            $table->dropIndex(['status', 'approval_requested_at']);
            $table->dropColumn(['approval_requested_at', 'approval_completed_at', 'approved_for_release_at', 'release_preparation_started_at', 'release_ready_at', 'release_risk_level', 'release_owner_id', 'latest_approval_result', 'approval_cycle_number']);
        });
    }
};
