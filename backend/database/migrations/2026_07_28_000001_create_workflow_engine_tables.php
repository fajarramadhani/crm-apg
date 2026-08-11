<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50);
            $table->string('name');
            $table->unsignedSmallInteger('version')->default(1);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['code', 'version']);
        });

        Schema::create('workflow_conditions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->string('name');
            $table->string('field', 100);
            $table->string('operator', 30);
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->string('stage_key', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->string('stage_type', 30)->default('normal');
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['workflow_id', 'stage_key']);
        });

        Schema::create('workflow_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->foreignId('from_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->foreignId('to_stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->string('action_key', 50);
            $table->string('name');
            $table->boolean('requires_notes')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['workflow_id', 'from_stage_id', 'action_key']);
        });

        Schema::create('workflow_transition_permissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();
            $table->string('role_key', 50)->nullable();
            $table->string('permission_code', 100)->nullable();
            $table->timestamps();
            $table->index(['transition_id', 'role_key']);
        });

        Schema::create('workflow_stage_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->string('field_name', 100);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
            $table->unique(['stage_id', 'field_name']);
        });

        Schema::create('workflow_transition_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transition_id')->constrained('workflow_transitions')->cascadeOnDelete();
            $table->string('recipient_type', 50);
            $table->string('channel', 30)->default('database');
            $table->string('template_code', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_approval_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflow_definitions')->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('workflow_stages')->cascadeOnDelete();
            $table->string('approval_type', 30)->default('single');
            $table->timestamps();
        });

        Schema::create('workflow_approval_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_config_id')->constrained('workflow_approval_configs')->cascadeOnDelete();
            $table->unsignedInteger('step_order')->default(1);
            $table->string('approver_role_key', 50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_approval_steps');
        Schema::dropIfExists('workflow_approval_configs');
        Schema::dropIfExists('workflow_transition_notifications');
        Schema::dropIfExists('workflow_stage_fields');
        Schema::dropIfExists('workflow_transition_permissions');
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_conditions');
        Schema::dropIfExists('workflow_definitions');
    }
};
