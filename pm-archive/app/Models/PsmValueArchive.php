<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PsmValueArchive extends Model
{
    protected $table = 'psm_value_archive';

    public $timestamps = false;

    protected $fillable = [
        'psm_val_id', 'psm_id', 'psm_var_id', 'variable_name', 'value', 'status', 'created_at'
    ];
}
