<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_request_history_challenges', function (Blueprint $table): void {
            $table->id();
            $table->char('challenge_token_hash', 64)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('identity_type', 20);
            $table->char('identity_hash', 64);
            $table->text('identity_ciphertext');
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts_remaining');
            $table->timestamp('resend_available_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'identity_type', 'identity_hash', 'created_at'], 'prh_ch_identity_lookup');
            $table->index(['expires_at', 'consumed_at', 'superseded_at'], 'prh_ch_cleanup');
        });

        Schema::create('public_request_history_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('identity_type', 20);
            $table->char('identity_hash', 64);
            $table->text('identity_ciphertext');
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['expires_at', 'revoked_at'], 'prh_access_cleanup');
            $table->index(['branch_id', 'identity_type', 'identity_hash'], 'prh_access_scope');
        });

        Schema::table('tickets', function (Blueprint $table): void {
            $table->index(['submission_source', 'branch_id', 'requester_email', 'submitted_at', 'id'], 'tickets_pub_email_hist');
            $table->index(['submission_source', 'branch_id', 'requester_phone', 'submitted_at', 'id'], 'tickets_pub_phone_hist');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_pub_email_hist');
            $table->dropIndex('tickets_pub_phone_hist');
        });
        Schema::dropIfExists('public_request_history_access_tokens');
        Schema::dropIfExists('public_request_history_challenges');
    }
};
