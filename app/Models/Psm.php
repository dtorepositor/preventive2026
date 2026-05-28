<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Psm extends Model
{
    protected $table = 'psm';

    const UPDATED_AT = null;

    protected $primaryKey = 'psm_id';

    protected $fillable = ['name', 'detail', 'enabled', 'type', 'template_psm_id', 'identifier', 'created_by', 'is_locked'];

    protected $casts = [
        'enabled' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Psm::class, 'template_psm_id', 'psm_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Psm::class, 'template_psm_id', 'psm_id');
    }

    public function variables(): HasMany
    {
        return $this->hasMany(PsmVariable::class, 'psm_id', 'psm_id');
    }

    public function values(): HasMany
    {
        return $this->hasMany(PsmValue::class, 'psm_id', 'psm_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getValueByVarName(string $name): ?string
    {
        $val = $this->values->first(fn (PsmValue $v) => $v->variable?->name === $name);
        return $val?->value;
    }

    public function getValueMap(): array
    {
        $this->load('values.variable');
        $map = [];
        foreach ($this->values as $v) {
            if ($v->variable) {
                $map[$v->variable->name] = $v->value;
            }
        }
        return $map;
    }

    public static function preventiveMaintenanceCategoryCode(?string $checklistType): string
    {
        $normalized = strtolower(trim((string) $checklistType));

        return match ($normalized) {
            'server' => 'SERVER',
            'ip_phone', 'ip-phone', 'ipphone' => 'IPPHONE',
            'network_device', 'network-device', 'networkdevice' => 'NETWORK',
            'wifi', 'wi-fi' => 'WIFI',
            'ups' => 'UPS',
            'cctv' => 'CCTV',
            default => 'PC',
        };
    }

    public function preventiveMaintenanceIdentifier(?string $checklistType = null): string
    {
        if (! empty($this->identifier)) {
            return (string) $this->identifier;
        }

        return $this->generatedPreventiveMaintenanceIdentifier($checklistType);
    }

    public function generatedPreventiveMaintenanceIdentifier(?string $checklistType = null): string
    {
        return sprintf(
            'PM%s-%04d',
            self::preventiveMaintenanceCategoryCode($checklistType),
            (int) $this->psm_id
        );
    }

    public function persistPreventiveMaintenanceIdentifier(?string $checklistType = null): string
    {
        $identifier = $this->generatedPreventiveMaintenanceIdentifier($checklistType);

        if (Schema::hasColumn($this->getTable(), 'identifier') && $this->identifier !== $identifier) {
            $this->forceFill(['identifier' => $identifier])->save();
        }

        return $identifier;
    }
}
