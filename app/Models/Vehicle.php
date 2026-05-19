<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Observers\VehicleObserver;
use Carbon\CarbonInterface;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vehicle (non-fiscal attributes; fiscally relevant data lives in {@see VehicleFiscalCharacteristics}).
 *
 * @property int $id
 * @property bool $is_exited Computed accessor: true iff `exit_date IS NOT NULL`.
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
#[ObservedBy([VehicleObserver::class])]
final class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    use SoftDeletes;

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
     * Historized chain of fiscal characteristics (non-overlapping periods).
     *
     * @return HasMany<VehicleFiscalCharacteristics, $this>
     */
    public function fiscalCharacteristics(): HasMany
    {
        return $this->hasMany(VehicleFiscalCharacteristics::class);
    }

    /**
     * Rental contracts on this vehicle (ADR-0014).
     *
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Unavailabilities on this vehicle.
     *
     * @return HasMany<Unavailability, $this>
     */
    public function unavailabilities(): HasMany
    {
        return $this->hasMany(Unavailability::class);
    }

    /**
     * Daily / weekly / monthly rates indexed by year (UNIQUE on `(vehicle_id, year)`).
     *
     * @return HasMany<VehicleYearlyPricing, $this>
     */
    public function yearlyPricings(): HasMany
    {
        return $this->hasMany(VehicleYearlyPricing::class);
    }

    /**
     * Whether the vehicle has exited the fleet (`exit_date IS NOT NULL`).
     *
     * Purely boolean; for filtering always prefer the date-aware scopes
     * {@see scopeActiveAt} and {@see scopeActiveDuring} (ADR-0018 D3).
     *
     * @return Attribute<bool, never>
     */
    protected function isExited(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->exit_date !== null,
        );
    }

    /**
     * Scope: vehicles active at the given date (`exit_date IS NULL OR exit_date >= $date`).
     * See ADR-0018 § 4 (date-aware visibility matrix).
     *
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeActiveAt(Builder $query, CarbonInterface $date): Builder
    {
        return $query->where(function (Builder $q) use ($date): void {
            $q->whereNull('exit_date')
                ->orWhere('exit_date', '>=', $date->toDateString());
        });
    }

    /**
     * Scope: vehicles active at any point of `[start, end]`
     * (`exit_date IS NULL OR exit_date >= $start`). See ADR-0018 § 4.
     *
     * @param  Builder<Vehicle>  $query
     * @return Builder<Vehicle>
     */
    public function scopeActiveDuring(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $this->scopeActiveAt($query, $start);
    }
}
