<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'reference')) {
                $table->string('reference', 255)->nullable()->after('affected_url');
            }
            if (Schema::hasColumn('tickets', 'ticket_category_id')) {
                $table->foreignId('ticket_category_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'reference')) {
                $table->dropColumn('reference');
            }
            if (Schema::hasColumn('tickets', 'ticket_category_id')) {
                $table->foreignId('ticket_category_id')->nullable(false)->change();
            }
        });
    }
};
