<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PsmVariable extends Model
{
    protected $table = 'psm_variable';

    const UPDATED_AT = null;

    protected $primaryKey = 'psm_var_id';

    protected $fillable = ['psm_id', 'name', 'description', 'enabled', 'input_type'];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public function psm(): BelongsTo
    {
        return $this->belongsTo(Psm::class, 'psm_id', 'psm_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PsmValue::class, 'psm_var_id', 'psm_var_id');
    }
}
