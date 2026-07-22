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
        Schema::create('ticket_sla_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('ticket_sla_id')->nullable()->index(); // nullable — no ticket_slas table
            $table->enum('sla_type', ['response', 'resolution'])->index();
            $table->enum('alert_level', ['approaching', 'critical', 'breached'])->index();
            $table->unsignedTinyInteger('threshold_percent');
            $table->unsignedInteger('elapsed_minutes');
            $table->unsignedInteger('target_minutes');
            $table->integer('remaining_minutes');
            $table->string('recipient_scope')->nullable();
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('deduplication_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_alerts');
    }
};
