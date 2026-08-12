<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('workflow_id')->nullable()->after('status')->constrained('workflow_definitions')->nullOnDelete();
            $table->unsignedSmallInteger('workflow_version')->nullable()->after('workflow_id');
            $table->json('workflow_snapshot')->nullable()->after('workflow_version');
            $table->string('workflow_mode', 20)->nullable()->after('workflow_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workflow_id');
            $table->dropColumn(['workflow_version', 'workflow_snapshot', 'workflow_mode']);
        });
    }
};
