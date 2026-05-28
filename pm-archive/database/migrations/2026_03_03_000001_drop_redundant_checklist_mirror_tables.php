<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_checklist_display')) {
            Schema::drop('item_checklist_display');
        }

        if (Schema::hasTable('item_checklist_entries')) {
            Schema::drop('item_checklist_entries');
        }

        if (Schema::hasTable('item_checklist_tasks')) {
            Schema::drop('item_checklist_tasks');
        }

        if (Schema::hasTable('item_checklists')) {
            Schema::drop('item_checklists');
        }

        if (Schema::hasTable('preventive_maintenance_checklists')) {
            Schema::drop('preventive_maintenance_checklists');
        }
    }

    public function down(): void
    {
        // Intentionally left empty. This migration removes legacy mirror tables.
    }
};
