<?php

namespace Database\Seeders;

use App\Data\ItemChecklistTemplate;
use App\Models\ItemChecklistItem;
use Illuminate\Database\Seeder;

class ItemChecklistItemSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['pc', 'server', 'ip_phone', 'network_device'] as $checklistType) {
            $entries = ItemChecklistTemplate::defaultEntriesForType($checklistType);

            foreach ($entries as $entry) {
                ItemChecklistItem::firstOrCreate(
                    [
                        'checklist_type' => $checklistType,
                        'item_no' => $entry['item_no'],
                        'task' => $entry['task'],
                        'description' => $entry['description'],
                    ],
                    [
                        'enabled' => true,
                        'sort_order' => $entry['sort_order'] ?? 0,
                    ]
                );
            }
        }
    }
}
