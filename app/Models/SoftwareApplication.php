<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoftwareApplication extends Model
{
    protected $table = 'software_applications';

    protected $fillable = ['name', 'description', 'enabled', 'sort_order'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

}
