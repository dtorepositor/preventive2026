<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperatingSystem extends Model
{
    protected $table = 'operating_systems';

    protected $fillable = ['name', 'description', 'enabled', 'sort_order'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

}
