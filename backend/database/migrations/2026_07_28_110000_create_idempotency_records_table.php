<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 160);
            $table->string('action', 80);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'endpoint', 'action', 'key_hash'], 'idempotency_records_scope_unique');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
