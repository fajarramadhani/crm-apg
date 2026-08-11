<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            if (! Schema::hasColumn('applications', 'system_type')) {
                $table->string('system_type', 50)->nullable()->after('description');
            }
            if (! Schema::hasColumn('applications', 'contact_person')) {
                $table->string('contact_person', 100)->nullable()->after('system_type');
            }
            if (! Schema::hasColumn('applications', 'vendor')) {
                $table->string('vendor', 100)->nullable()->after('contact_person');
            }
            if (! Schema::hasColumn('applications', 'tags')) {
                $table->json('tags')->nullable()->after('vendor');
            }
        });

        Schema::table('ticket_categories', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_categories', 'default_workflow_id')) {
                $table->foreignId('default_workflow_id')->nullable()->after('description')->constrained('workflow_definitions')->nullOnDelete();
            }
            if (! Schema::hasColumn('ticket_categories', 'metadata')) {
                $table->json('metadata')->nullable()->after('default_workflow_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_categories', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_categories', 'default_workflow_id')) {
                $table->dropConstrainedForeignId('default_workflow_id');
            }
            if (Schema::hasColumn('ticket_categories', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        Schema::table('applications', function (Blueprint $table): void {
            $columnsToDrop = [];
            if (Schema::hasColumn('applications', 'system_type')) {
                $columnsToDrop[] = 'system_type';
            }
            if (Schema::hasColumn('applications', 'contact_person')) {
                $columnsToDrop[] = 'contact_person';
            }
            if (Schema::hasColumn('applications', 'vendor')) {
                $columnsToDrop[] = 'vendor';
            }
            if (Schema::hasColumn('applications', 'tags')) {
                $columnsToDrop[] = 'tags';
            }
            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
