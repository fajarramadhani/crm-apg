<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->enum('office_type', ['pusat', 'cabang']);
            $table->timestamps();

            $table->unique(['name', 'office_type']);
            $table->index('office_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
