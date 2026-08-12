<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            // Current stage for dynamic workflow tickets
            $table->string('current_workflow_stage', 50)->nullable()->after('workflow_mode')->index();
            // Previous stage stored for on_hold resume
            $table->string('workflow_previous_stage', 50)->nullable()->after('current_workflow_stage');
            // Timestamp when current stage was entered
            $table->timestamp('workflow_stage_entered_at')->nullable()->after('workflow_previous_stage');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['current_workflow_stage']);
            $table->dropColumn([
                'current_workflow_stage',
                'workflow_previous_stage',
                'workflow_stage_entered_at',
            ]);
        });
    }
};
