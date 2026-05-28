<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpPhoneItemChecklistItem extends Model
{
    protected $table = 'ip_phone_item_checklist_items';

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
