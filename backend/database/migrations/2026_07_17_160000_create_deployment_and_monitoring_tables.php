<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->unsignedInteger('deployment_cycle_number')->default(0)->after('status');
            $table->timestamp('deployment_scheduled_at')->nullable();
            $table->timestamp('deployment_started_at')->nullable();
            $table->timestamp('deployed_at')->nullable();
            $table->timestamp('monitoring_started_at')->nullable();
            $table->timestamp('monitoring_completed_at')->nullable();
            $table->timestamp('requester_confirmation_requested_at')->nullable();
            $table->timestamp('requester_confirmed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('latest_deployment_result')->nullable();
            $table->foreignId('current_deployment_id')->nullable(); // will constrain later or leave as regular field to avoid circular dependency
            $table->foreignId('current_monitoring_session_id')->nullable();
            $table->string('post_release_status')->nullable();
        });

        Schema::create('ticket_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_plan_id')->constrained('ticket_release_plans')->restrictOnDelete();
            $table->foreignId('rollback_plan_id')->constrained('ticket_rollback_plans')->restrictOnDelete();

            $table->unsignedInteger('cycle_number');
            $table->unsignedInteger('deployment_number');
            $table->string('environment'); // staging, production
            $table->string('release_version');

            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();

            $table->foreignId('deployment_owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('release_owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();

            $table->string('status');
            $table->text('deployment_summary');
            $table->string('execution_reference')->nullable();
            $table->string('change_reference')->nullable();
            $table->text('result_summary')->nullable();
            $table->text('failure_reason')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['ticket_id', 'deployment_number']);
        });

        // Add constraints to tickets
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('current_deployment_id')->references('id')->on('ticket_deployments')->nullOnDelete();
        });

        Schema::create('ticket_deployment_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained('ticket_deployments')->cascadeOnDelete();
            $table->unsignedInteger('step_number');
            $table->string('title');
            $table->text('description');
            $table->string('step_type');
            $table->boolean('is_required')->default(true);
            $table->string('status');

            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('evidence_attachment_id')->nullable()->constrained('ticket_attachments')->nullOnDelete();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['deployment_id', 'step_number']);
        });

        Schema::create('ticket_deployment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deployment_id')->constrained('ticket_deployments')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('ticket_rollback_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('ticket_deployments')->cascadeOnDelete();
            $table->foreignId('rollback_plan_id')->constrained('ticket_rollback_plans')->restrictOnDelete();

            $table->unsignedInteger('rollback_number');

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->text('reason');
            $table->string('trigger_source'); // deployment_failure, monitoring_issue, manual_decision

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('status');
            $table->text('result_summary')->nullable();
            $table->text('failure_reason')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['ticket_id', 'rollback_number']);
            $table->unique(['deployment_id'], 'rollback_deployment_unique'); // One rollback per deployment
        });

        Schema::create('ticket_monitoring_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('ticket_deployments')->cascadeOnDelete();

            $table->unsignedInteger('cycle_number');

            $table->foreignId('started_by')->constrained('users')->restrictOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('planned_end_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->string('status');
            $table->string('overall_result')->nullable();
            $table->text('summary')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('current_monitoring_session_id')->references('id')->on('ticket_monitoring_sessions')->nullOnDelete();
        });

        Schema::create('ticket_monitoring_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monitoring_session_id')->constrained('ticket_monitoring_sessions')->cascadeOnDelete();
            $table->unsignedInteger('check_number');
            $table->string('category');
            $table->string('title');
            $table->text('description');
            $table->text('expected_condition');
            $table->text('actual_result')->nullable();
            $table->string('status');

            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('evidence_attachment_id')->nullable()->constrained('ticket_attachments')->nullOnDelete();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['monitoring_session_id', 'check_number'], 'mc_session_check_unique');
        });

        Schema::create('ticket_post_release_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('ticket_deployments')->cascadeOnDelete();
            $table->foreignId('monitoring_session_id')->constrained('ticket_monitoring_sessions')->cascadeOnDelete();

            $table->unsignedInteger('incident_number');
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->restrictOnDelete();

            $table->string('title');
            $table->text('description');
            $table->string('business_impact');
            $table->string('severity'); // critical, major, minor, cosmetic
            $table->string('status');
            $table->boolean('requires_rollback')->default(false);

            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();

            $table->unique(['ticket_id', 'incident_number'], 'pri_ticket_incident_unique');
        });

        Schema::create('ticket_requester_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->constrained('ticket_deployments')->cascadeOnDelete();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();

            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();

            $table->string('status');
            $table->text('confirmation_notes')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->unsignedInteger('version')->default(1);
            $table->timestamps();

            $table->unique(['ticket_id', 'deployment_id'], 'trc_ticket_deployment_unique');
        });

        Schema::create('ticket_closures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deployment_id')->nullable()->constrained('ticket_deployments')->nullOnDelete();
            $table->foreignId('monitoring_session_id')->nullable()->constrained('ticket_monitoring_sessions')->nullOnDelete();
            $table->foreignId('requester_confirmation_id')->nullable()->constrained('ticket_requester_confirmations')->nullOnDelete();

            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');

            $table->text('closure_summary');
            $table->text('resolution_summary');
            $table->text('business_outcome');

            $table->string('final_sla_result')->nullable();
            $table->string('final_status');

            $table->timestamps();

            $table->unique(['ticket_id'], 'tc_ticket_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_closures');
        Schema::dropIfExists('ticket_requester_confirmations');
        Schema::dropIfExists('ticket_post_release_incidents');
        Schema::dropIfExists('ticket_monitoring_checks');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['current_monitoring_session_id']);
        });
        Schema::dropIfExists('ticket_monitoring_sessions');

        Schema::dropIfExists('ticket_rollback_executions');
        Schema::dropIfExists('ticket_deployment_histories');
        Schema::dropIfExists('ticket_deployment_steps');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['current_deployment_id']);
        });
        Schema::dropIfExists('ticket_deployments');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn([
                'deployment_cycle_number',
                'deployment_scheduled_at',
                'deployment_started_at',
                'deployed_at',
                'monitoring_started_at',
                'monitoring_completed_at',
                'requester_confirmation_requested_at',
                'requester_confirmed_at',
                'closed_by',
                'latest_deployment_result',
                'current_deployment_id',
                'current_monitoring_session_id',
                'post_release_status',
            ]);
        });
    }
};
