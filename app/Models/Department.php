<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    protected $fillable = [
        'college_office_id',
        'name',
    ];

    public function collegeOffice(): BelongsTo
    {
        return $this->belongsTo(CollegeOffice::class);
    }
}
