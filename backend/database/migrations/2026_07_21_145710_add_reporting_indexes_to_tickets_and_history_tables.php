<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index('application_id', 'tickets_app_id_idx');
            $table->index('division_id', 'tickets_div_id_idx');
            $table->index('final_priority_id', 'tickets_final_prio_idx');
            $table->index('closed_at', 'tickets_closed_at_idx');
            $table->index('response_due_at', 'tickets_resp_due_idx');
            $table->index('resolution_due_at', 'tickets_reso_due_idx');
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->index('action', 'tsh_action_idx');
            $table->index('to_status', 'tsh_to_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_app_id_idx');
            $table->dropIndex('tickets_div_id_idx');
            $table->dropIndex('tickets_final_prio_idx');
            $table->dropIndex('tickets_closed_at_idx');
            $table->dropIndex('tickets_resp_due_idx');
            $table->dropIndex('tickets_reso_due_idx');
        });

        Schema::table('ticket_status_histories', function (Blueprint $table) {
            $table->dropIndex('tsh_action_idx');
            $table->dropIndex('tsh_to_status_idx');
        });
    }
};
