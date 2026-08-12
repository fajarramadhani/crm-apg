<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('event_type', 100);
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->string('recipient_hash', 64)->index();
            $table->string('recipient_last_four', 4)->nullable();
            $table->text('recipient_encrypted')->nullable();
            $table->string('recipient_role', 50);
            $table->string('recipient_type', 50);
            $table->string('template_name', 100);
            $table->text('rendered_message');
            $table->string('deduplication_key', 191)->unique();
            $table->string('provider', 50)->default('fonnte');
            $table->string('provider_message_id', 191)->nullable()->index();
            $table->json('provider_message_ids')->nullable();
            $table->string('provider_request_id', 191)->nullable()->index();
            $table->json('provider_response')->nullable();
            $table->enum('status', ['queued', 'processing', 'sent', 'pending', 'failed', 'invalid', 'expired'])->default('queued')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('processing_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->text('failure_message_safe')->nullable();
            $table->timestamps();

            $table->index(['created_at']);
            $table->unique(['provider', 'provider_message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
