<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_assignments', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_assignments', 'role_at_assignment')) {
                $table->string('role_at_assignment', 50)->nullable()->after('assignment_type');
            }
            if (! Schema::hasColumn('ticket_assignments', 'acting_as_pic')) {
                $table->boolean('acting_as_pic')->default(false)->after('role_at_assignment');
            }
            if (! Schema::hasColumn('ticket_assignments', 'target_completed_at')) {
                $table->timestamp('target_completed_at')->nullable()->after('ended_at');
            }
            if (! Schema::hasColumn('ticket_assignments', 'assignment_reason')) {
                $table->text('assignment_reason')->nullable()->after('notes');
            }
        });

        Schema::create('ticket_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('ticket_assignments')->nullOnDelete();
            $table->string('action', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignment_type', 30)->default('primary');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at']);
            $table->index(['actor_id']);
            $table->index(['assignment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignment_histories');

        Schema::table('ticket_assignments', function (Blueprint $table): void {
            $columnsToDrop = [];
            if (Schema::hasColumn('ticket_assignments', 'role_at_assignment')) {
                $columnsToDrop[] = 'role_at_assignment';
            }
            if (Schema::hasColumn('ticket_assignments', 'acting_as_pic')) {
                $columnsToDrop[] = 'acting_as_pic';
            }
            if (Schema::hasColumn('ticket_assignments', 'target_completed_at')) {
                $columnsToDrop[] = 'target_completed_at';
            }
            if (Schema::hasColumn('ticket_assignments', 'assignment_reason')) {
                $columnsToDrop[] = 'assignment_reason';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
