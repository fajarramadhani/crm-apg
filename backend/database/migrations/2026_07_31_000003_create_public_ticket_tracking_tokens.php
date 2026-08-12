<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_ticket_tracking_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->char('derivation_nonce', 64)->unique();
            $table->unsignedInteger('generation');
            $table->unsignedSmallInteger('key_version');
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'generation']);
            $table->index(['ticket_id', 'revoked_at', 'expires_at'], 'public_tracking_active_lookup');
        });

        Schema::table('public_ticket_submissions', function (Blueprint $table): void {
            $table->foreignId('tracking_token_id')->nullable()->after('ticket_id')
                ->constrained('public_ticket_tracking_tokens')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('public_ticket_submissions')) {
            Schema::table('public_ticket_submissions', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('tracking_token_id');
            });
        }

        Schema::dropIfExists('public_ticket_tracking_tokens');
    }
};
