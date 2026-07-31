<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_post_release_incidents', function (Blueprint $table): void {
            $table->string('incident_number', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_post_release_incidents', function (Blueprint $table): void {
            $table->unsignedInteger('incident_number')->change();
        });
    }
};
