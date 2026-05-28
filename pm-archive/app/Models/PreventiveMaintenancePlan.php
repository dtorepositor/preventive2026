<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PreventiveMaintenancePlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const SCHEDULE_CATEGORY_MONTHLY = 'monthly';

    public const SCHEDULE_CATEGORY_QUARTERLY = 'quarterly';

    public const SCHEDULE_CATEGORY_HALF_QUARTER = 'half_quarter';

    public const SCHEDULE_CATEGORY_YEARLY = 'yearly';

    public const SCHEDULE_CATEGORIES = [
        self::SCHEDULE_CATEGORY_MONTHLY,
        self::SCHEDULE_CATEGORY_QUARTERLY,
        self::SCHEDULE_CATEGORY_HALF_QUARTER,
        self::SCHEDULE_CATEGORY_YEARLY,
    ];

    protected $fillable = [
        'name',
        'year',
        'schedule_category',
    ];

    protected $casts = [
        'year' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function schedules(): HasMany
    {
        return $this->hasMany(PreventiveMaintenancePlanSchedule::class)
            ->orderBy('month')
            ->orderBy('office_name');
    }
}
