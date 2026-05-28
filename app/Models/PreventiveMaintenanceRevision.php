<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventiveMaintenanceRevision extends Model
{
    protected $fillable = [
        'psm_id',
        'name',
        'detail',
        'values_snapshot',
        'original_created_at',
    ];

    protected $casts = [
        'values_snapshot' => 'array',
        'original_created_at' => 'datetime',
    ];

    public function preventiveMaintenance(): BelongsTo
    {
        return $this->belongsTo(Psm::class, 'psm_id', 'psm_id');
    }
}
