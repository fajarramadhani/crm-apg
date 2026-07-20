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
        Schema::table('ticket_release_plans', function (Blueprint $table) {
            $table->json('pre_deployment_steps')->nullable();
            $table->json('deployment_steps')->nullable(); // Set as nullable initially so we can migrate existing data safely, though it's logically required. Or just give it a default. Let's make it nullable and enforce in code, or nullable during migration then update.
            $table->json('database_execution_steps')->nullable();
            $table->json('post_deployment_steps')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_release_plans', function (Blueprint $table) {
            $table->dropColumn([
                'pre_deployment_steps',
                'deployment_steps',
                'database_execution_steps',
                'post_deployment_steps',
            ]);
        });
    }
};
