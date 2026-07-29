<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_deployments', function (Blueprint $table): void {
            $table->string('deployment_number', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_deployments', function (Blueprint $table): void {
            $table->unsignedInteger('deployment_number')->change();
        });
    }
};
