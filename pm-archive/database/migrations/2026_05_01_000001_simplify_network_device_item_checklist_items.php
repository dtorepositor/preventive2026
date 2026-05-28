<?php

use App\Data\ItemChecklistTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->syncNetworkDeviceRows('network_device_item_checklist_items', false);
        $this->syncNetworkDeviceRows('item_checklist_items', true);
    }

    public function down(): void
    {
        if (Schema::hasTable('network_device_item_checklist_items')) {
            DB::table('network_device_item_checklist_items')->update(['enabled' => true]);
        }

        if (Schema::hasTable('item_checklist_items') && Schema::hasColumn('item_checklist_items', 'checklist_type')) {
            DB::table('item_checklist_items')
                ->where('checklist_type', 'network_device')
                ->update(['enabled' => true]);
        }
    }

    private function syncNetworkDeviceRows(string $table, bool $usesChecklistType): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if ($usesChecklistType && ! Schema::hasColumn($table, 'checklist_type')) {
            return;
        }

        $query = DB::table($table);
        if ($usesChecklistType) {
            $query->where('checklist_type', 'network_device');
        }
        $query->update(['enabled' => false, 'updated_at' => now()]);

        foreach (ItemChecklistTemplate::defaultEntriesForType('network_device') as $entry) {
            $keys = [
                'item_no' => $entry['item_no'],
                'task' => $entry['task'],
                'description' => $entry['description'],
            ];

            if ($usesChecklistType) {
                $keys['checklist_type'] = 'network_device';
            }

            DB::table($table)->updateOrInsert(
                $keys,
                [
                    'enabled' => true,
                    'sort_order' => $entry['sort_order'] ?? 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }
};
