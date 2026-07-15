<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('sla_policy_id')->nullable()->after('final_priority_id')->constrained('sla_policies')->nullOnDelete();
            $table->foreignId('working_calendar_id')->nullable()->after('sla_policy_id')->constrained('working_calendars')->nullOnDelete();
            $table->timestamp('response_due_at')->nullable()->after('working_calendar_id');
            $table->timestamp('resolution_due_at')->nullable()->after('response_due_at');
            $table->string('sla_timezone', 64)->nullable()->after('resolution_due_at');
            $table->timestamp('triage_started_at')->nullable()->after('validated_at');
            $table->timestamp('assigned_at')->nullable()->after('triage_started_at');
            $table->foreignId('current_assignee_id')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('current_assignee_id')->constrained('users')->nullOnDelete();
            $table->index(['status', 'submitted_at']);
            $table->index(['current_assignee_id', 'status']);
        });
        Schema::create('ticket_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users');
            $table->foreignId('assigned_by')->constrained('users');
            $table->string('assignment_type', 20)->default('primary');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['ticket_id', 'assignment_type', 'is_current']);
            $table->index(['assigned_to', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['status', 'submitted_at']);
            $table->dropIndex(['current_assignee_id', 'status']);
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropConstrainedForeignId('current_assignee_id');
            $table->dropConstrainedForeignId('working_calendar_id');
            $table->dropConstrainedForeignId('sla_policy_id');
            $table->dropColumn(['response_due_at', 'resolution_due_at', 'sla_timezone', 'triage_started_at', 'assigned_at']);
        });
    }
};
