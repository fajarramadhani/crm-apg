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
        Schema::create('notification_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('notification_id')->nullable()->index(); // Can be null if skipped
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type')->index();
            $table->string('delivery_channel')->default('database');
            $table->enum('status', ['pending', 'delivered', 'skipped', 'failed'])->index();
            $table->string('deduplication_key')->unique();
            $table->timestamp('delivered_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_logs');
    }
};
