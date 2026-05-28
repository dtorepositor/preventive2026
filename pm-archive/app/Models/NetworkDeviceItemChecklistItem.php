<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkDeviceItemChecklistItem extends Model
{
    protected $table = 'network_device_item_checklist_items';

    protected $fillable = [
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
