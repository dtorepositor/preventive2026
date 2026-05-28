<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\PsmValueArchive;


class PsmValue extends Model
{
    protected $table = 'psm_value';

    const UPDATED_AT = null;

    protected $primaryKey = 'psm_val_id';

    protected $fillable = ['psm_id', 'psm_var_id', 'value', 'status'];

    public function psm(): BelongsTo
    {
        return $this->belongsTo(Psm::class, 'psm_id', 'psm_id');
    }

    public function variable(): BelongsTo
    {
        return $this->belongsTo(PsmVariable::class, 'psm_var_id', 'psm_var_id');
    }

    protected static function booted()
    {
        static::saved(function (self $model) {
            try {
                PsmValueArchive::updateOrCreate([
                    'psm_id' => $model->psm_id,
                    'psm_var_id' => $model->psm_var_id,
                ], [
                    'psm_val_id' => $model->{$model->getKeyName()} ?? null,
                    'variable_name' => $model->variable?->name ?? null,
                    'value' => $model->value,
                    'status' => $model->status,
                    'created_at' => $model->created_at ?? now(),
                ]);
            } catch (\Throwable $e) {
                // Don't break main flow if archive write fails
            }
        });
    }
}
