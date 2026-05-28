<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecificationCategory extends Model
{
    protected $table = 'specification_categories';

    protected $fillable = [
        'name',
        'label',
        'description',
        'category_type',
        'enabled',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
