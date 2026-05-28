<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreventiveMaintenancePlanSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'preventive_maintenance_plan_id',
        'month',
        'office_name',
    ];

    protected $casts = [
        'month' => 'integer',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PreventiveMaintenancePlan::class, 'preventive_maintenance_plan_id');
    }
}
