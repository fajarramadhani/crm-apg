<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_ticket_action_challenges', function (Blueprint $table): void {
            $table->id();
            $table->char('derivation_nonce', 64)->unique();
            $table->char('challenge_token_hash', 64)->unique();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_tracking_token_id')->constrained('public_ticket_tracking_tokens', indexName: 'pta_challenge_tracking_fk')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->char('identity_hash', 64);
            $table->text('identity_ciphertext');
            $table->string('action', 20);
            $table->char('status_hash', 64);
            $table->string('otp_hash');
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts_remaining');
            $table->timestamp('resend_available_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->index(['public_tracking_token_id', 'action', 'identity_hash'], 'pta_challenge_scope');
            $table->index(['expires_at', 'consumed_at', 'superseded_at'], 'pta_challenge_cleanup');
        });

        Schema::create('public_ticket_action_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_tracking_token_id')->constrained('public_ticket_tracking_tokens', indexName: 'pta_access_tracking_fk')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->char('identity_hash', 64);
            $table->string('action', 20);
            $table->char('status_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['public_tracking_token_id', 'action', 'expires_at'], 'pta_access_scope');
            $table->index(['expires_at', 'revoked_at'], 'pta_access_cleanup');
        });

        Schema::create('public_ticket_action_idempotencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('public_tracking_token_id')->constrained('public_ticket_tracking_tokens', indexName: 'pta_idem_tracking_fk')->cascadeOnDelete();
            $table->foreignId('public_action_access_token_id')->constrained('public_ticket_action_access_tokens', indexName: 'pta_idem_access_fk')->cascadeOnDelete();
            $table->string('action', 20);
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->json('result')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['public_action_access_token_id', 'action', 'key_hash'], 'pta_idempotency_unique');
            $table->index('expires_at');
        });

        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });
        Schema::table('ticket_requester_confirmations', function (Blueprint $table): void {
            $table->unsignedBigInteger('requester_id')->nullable()->change();
            $table->unsignedBigInteger('deployment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (
            DB::table('ticket_comments')->whereNull('user_id')->exists()
            || DB::table('ticket_requester_confirmations')->whereNull('requester_id')->orWhereNull('deployment_id')->exists()
        ) {
            throw new RuntimeException('Cannot roll back public ticket actions while public requester responses exist.');
        }

        Schema::table('ticket_requester_confirmations', function (Blueprint $table): void {
            $table->unsignedBigInteger('deployment_id')->nullable(false)->change();
            $table->unsignedBigInteger('requester_id')->nullable(false)->change();
        });
        Schema::table('ticket_comments', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
        Schema::dropIfExists('public_ticket_action_idempotencies');
        Schema::dropIfExists('public_ticket_action_access_tokens');
        Schema::dropIfExists('public_ticket_action_challenges');
    }
};
