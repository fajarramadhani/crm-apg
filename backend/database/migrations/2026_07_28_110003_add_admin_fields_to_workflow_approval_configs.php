<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_approval_configs', function (Blueprint $table): void {
            $table->string('label', 150)->default('Supervisor IT Approval')->after('approval_type');
            $table->boolean('notes_required')->default(false)->after('label');
            $table->boolean('is_active')->default(true)->after('notes_required');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_approval_configs', function (Blueprint $table): void {
            $table->dropColumn(['label', 'notes_required', 'is_active']);
        });
    }
};
