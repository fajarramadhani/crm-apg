<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table): void {
            // Lifecycle status: draft → published → (active | inactive)
            $table->string('config_status', 20)->default('draft')->after('is_active')->index();
            $table->timestamp('published_at')->nullable()->after('config_status');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table): void {
            $table->dropIndex(['config_status']);
            $table->dropColumn(['config_status', 'published_at']);
        });
    }
};
