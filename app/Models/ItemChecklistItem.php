<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemChecklistItem extends Model
{
    protected $table = 'item_checklist_items';

    protected $fillable = [
        'checklist_type',
        'item_no',
        'task',
        'description',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
