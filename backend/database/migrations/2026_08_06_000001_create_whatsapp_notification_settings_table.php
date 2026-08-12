<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 20)->default('global')->unique();
            $table->boolean('enabled')->nullable();
            $table->json('events')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_settings');
    }
};
