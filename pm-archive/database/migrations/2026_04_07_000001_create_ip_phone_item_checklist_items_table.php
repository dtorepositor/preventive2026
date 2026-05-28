<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ip_phone_item_checklist_items')) {
            Schema::create('ip_phone_item_checklist_items', function (Blueprint $table) {
                $table->id();
                $table->integer('item_no');
                $table->string('task');
                $table->text('description');
                $table->boolean('enabled')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['enabled', 'item_no', 'sort_order'], 'ip_phone_item_checklist_items_enabled_sort_idx');
            });
        }

        // Migrate any existing rows from item_checklist_items that were stored as ip_phone type
        if (Schema::hasTable('item_checklist_items') && Schema::hasColumn('item_checklist_items', 'checklist_type')) {
            $rows = DB::table('item_checklist_items')
                ->where('checklist_type', 'ip_phone')
                ->orderBy('item_no')
                ->orderBy('sort_order')
                ->get(['item_no', 'task', 'description', 'enabled', 'sort_order', 'created_at', 'updated_at']);

            foreach ($rows as $row) {
                DB::table('ip_phone_item_checklist_items')->updateOrInsert(
                    [
                        'item_no' => $row->item_no,
                        'task' => $row->task,
                        'description' => $row->description,
                    ],
                    [
                        'enabled' => $row->enabled,
                        'sort_order' => $row->sort_order,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ip_phone_item_checklist_items');
    }
};
