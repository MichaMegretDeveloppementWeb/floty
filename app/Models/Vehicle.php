<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Véhicule (attributs non fiscaux — les caractéristiques fiscalement
 * déterminantes sont dans {@see VehicleFiscalCharacteristics}).
 *
 * Cf. 01-schema-metier.md § 2.
 *
 * @property int $id
 * @property string $license_plate
 * @property string $brand
 * @property string $model
 * @property string|null $vin
 * @property string|null $color
 * @property string|null $photo_path
 * @property Carbon $first_french_registration_date
 * @property Carbon $first_origin_registration_date
 * @property Carbon $first_economic_use_date
 * @property Carbon $acquisition_date
 * @property Carbon|null $exit_date
 * @property VehicleExitReason|null $exit_reason
 * @property VehicleStatus $current_status
 * @property int|null $mileage_current
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'license_plate',
    'brand',
    'model',
    'vin',
    'color',
    'photo_path',
    'first_french_registration_date',
    'first_origin_registration_date',
    'first_economic_use_date',
    'acquisition_date',
    'exit_date',
    'exit_reason',
    'current_status',
    'mileage_current',
    'notes',
])]
class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_french_registration_date' => 'date',
            'first_origin_registration_date' => 'date',
            'first_economic_use_date' => 'date',
            'acquisition_date' => 'date',
            'exit_date' => 'date',
            'exit_reason' => VehicleExitReason::class,
            'current_status' => VehicleStatus::class,
        ];
    }

    /**
     * Chaîne historisée des caractéristiques fiscales (périodes qui ne se
     * chevauchent jamais).
     *
     * @return HasMany<VehicleFiscalCharacteristics, $this>
     */
    public function fiscalCharacteristics(): HasMany
    {
        return $this->hasMany(VehicleFiscalCharacteristics::class);
    }

    /**
     * Attributions du véhicule.
     *
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * Indisponibilités du véhicule.
     *
     * @return HasMany<Unavailability, $this>
     */
    public function unavailabilities(): HasMany
    {
        return $this->hasMany(Unavailability::class);
    }
}
