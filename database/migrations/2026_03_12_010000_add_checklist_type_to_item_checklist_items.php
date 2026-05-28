<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('item_checklist_items')) {
            return;
        }

        Schema::table('item_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('item_checklist_items', 'checklist_type')) {
                $table->string('checklist_type', 20)->default('pc')->after('id');
                $table->index(['checklist_type', 'enabled', 'sort_order'], 'item_checklist_items_type_enabled_sort_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('item_checklist_items') || ! Schema::hasColumn('item_checklist_items', 'checklist_type')) {
            return;
        }

        Schema::table('item_checklist_items', function (Blueprint $table) {
            $table->dropIndex('item_checklist_items_type_enabled_sort_idx');
            $table->dropColumn('checklist_type');
        });
    }
};