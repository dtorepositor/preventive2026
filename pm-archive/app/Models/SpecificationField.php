<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecificationField extends Model
{
    protected $table = 'specification_fields';

    protected $fillable = ['name', 'label', 'description', 'input_type', 'placeholder', 'enabled', 'sort_order'];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}
