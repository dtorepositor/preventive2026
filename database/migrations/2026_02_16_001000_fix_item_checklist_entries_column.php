<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_checklist_entries') && ! Schema::hasColumn('item_checklist_entries', 'item_checklist_task_id')) {
            Schema::table('item_checklist_entries', function (Blueprint $table) {
                // Add as nullable first to avoid constraint failures with any legacy rows.
                $table->unsignedBigInteger('item_checklist_task_id')->nullable()->after('id');
            });

            // If the tasks table exists, add the foreign key separately (ignore errors for existing orphan rows).
            if (Schema::hasTable('item_checklist_tasks')) {
                Schema::table('item_checklist_entries', function (Blueprint $table) {
                    $table->foreign('item_checklist_task_id')->references('id')->on('item_checklist_tasks')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('item_checklist_entries') && Schema::hasColumn('item_checklist_entries', 'item_checklist_task_id')) {
            Schema::table('item_checklist_entries', function (Blueprint $table) {
                // Drop FK if it exists; ignore if it was not created.
                try {
                    $table->dropForeign(['item_checklist_task_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
                $table->dropColumn('item_checklist_task_id');
            });
        }
    }
};
