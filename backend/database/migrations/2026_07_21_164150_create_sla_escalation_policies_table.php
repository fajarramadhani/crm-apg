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
        Schema::create('sla_escalation_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('priority')->nullable()->index();
            $table->enum('sla_type', ['response', 'resolution'])->index();
            $table->unsignedTinyInteger('warning_threshold_percent')->default(75);
            $table->unsignedTinyInteger('critical_threshold_percent')->default(90);
            $table->unsignedInteger('inactivity_threshold_minutes')->nullable();
            $table->boolean('escalate_to_it_lead')->default(false);
            $table->boolean('escalate_to_manager')->default(false);
            $table->boolean('escalate_to_supervisor')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Allow multiple policies for a priority/sla_type, but typically only one active
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sla_escalation_policies');
    }
};
