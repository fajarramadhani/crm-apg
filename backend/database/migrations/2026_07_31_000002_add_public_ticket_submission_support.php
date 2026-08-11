<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->unsignedBigInteger('requester_id')->nullable()->change();
            $table->string('requester_name', 150)->nullable()->after('requester_id');
            $table->string('requester_email')->nullable()->after('requester_name');
            $table->string('requester_phone', 20)->nullable()->after('requester_email');
            $table->string('submission_source', 40)->default('authenticated_requester')->index()->after('requester_phone');
        });

        DB::table('tickets')->whereNotNull('requester_id')->whereNull('requester_name')->update([
            'requester_name' => DB::raw('(select name from users where users.id = tickets.requester_id)'),
            'requester_email' => DB::raw('(select email from users where users.id = tickets.requester_id)'),
            'requester_phone' => DB::raw('(select phone from users where users.id = tickets.requester_id)'),
        ]);

        Schema::table('ticket_status_histories', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->change();
        });
        Schema::table('ticket_attachments', function (Blueprint $table): void {
            $table->unsignedBigInteger('uploaded_by')->nullable()->change();
        });

        Schema::create('public_ticket_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->string('request_hash', 64);
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && DB::table('tickets')->whereNull('requester_id')->exists()) {
            throw new RuntimeException('Cannot roll back public ticket support while public tickets exist.');
        }

        Schema::dropIfExists('public_ticket_submissions');

        Schema::table('ticket_status_histories', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable(false)->change();
        });
        Schema::table('ticket_attachments', function (Blueprint $table): void {
            $table->unsignedBigInteger('uploaded_by')->nullable(false)->change();
        });
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex(['submission_source']);
            $table->dropColumn(['requester_name', 'requester_email', 'requester_phone', 'submission_source']);
            $table->unsignedBigInteger('requester_id')->nullable(false)->change();
        });
    }
};
