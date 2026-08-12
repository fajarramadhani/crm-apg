<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_records', function (Blueprint $table): void {
            $table->foreignId('public_tracking_token_id')->nullable()->after('ticket_id')
                ->constrained('public_ticket_tracking_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_records', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('public_tracking_token_id');
        });
    }
};
