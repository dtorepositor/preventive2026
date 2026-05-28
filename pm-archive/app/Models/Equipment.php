<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipment';

    protected $fillable = ['name', 'description', 'enabled', 'sort_order'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

}
