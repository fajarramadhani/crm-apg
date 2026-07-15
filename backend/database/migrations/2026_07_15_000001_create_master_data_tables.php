<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('owner_division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('application_modules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['application_id', 'code']);
        });

        Schema::create('ticket_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('type', 30)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ticket_priorities', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('level')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('working_calendars', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name');
            $table->string('timezone')->default('Asia/Jakarta');
            $table->time('workday_start');
            $table->time('workday_end');
            $table->json('working_days');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sla_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('priority_id')->constrained('ticket_priorities')->restrictOnDelete();
            $table->unsignedInteger('response_minutes')->nullable();
            $table->unsignedInteger('resolution_minutes');
            $table->foreignId('working_calendar_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('working_calendar_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->string('name');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();
            $table->unique(['working_calendar_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('division_id')->nullable()->after('role_id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('division_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('division_id');
        });
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('sla_policies');
        Schema::dropIfExists('working_calendars');
        Schema::dropIfExists('ticket_priorities');
        Schema::dropIfExists('ticket_categories');
        Schema::dropIfExists('application_modules');
        Schema::dropIfExists('applications');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('divisions');
    }
};
