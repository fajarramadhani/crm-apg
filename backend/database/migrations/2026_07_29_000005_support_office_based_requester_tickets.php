<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('office_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()->change();
            $table->foreignId('current_division_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (DB::table('tickets')->whereNull('division_id')->orWhereNull('current_division_id')->exists()) {
            throw new RuntimeException('Rollback tidak aman karena terdapat tiket Requester berbasis Office tanpa division.');
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('office_id');
            $table->foreignId('division_id')->nullable(false)->change();
            $table->foreignId('current_division_id')->nullable(false)->change();
        });
    }
};
