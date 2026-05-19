<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\Vehicle\DeleteFiscalCharacteristicsAction;
use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleUserType;
use App\Observers\VehicleFiscalCharacteristicsObserver;
use Database\Factories\VehicleFiscalCharacteristicsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Effective version of a vehicle's fiscal characteristics over a period
 * (`effective_from` → `effective_to`, inclusive).
 *
 * Periods for the same vehicle never overlap (triple protection: application
 * service, MySQL trigger, pessimistic lock).
 *
 * No soft-delete: deleting a historic version is a hard-delete (gated by the
 * absence of an overlapping contract, see {@see DeleteFiscalCharacteristicsAction}).
 * The "current" version is never deleted: it is either corrected in place or
 * replaced by creating a new version.
 *
 * @property int $id
 * @property int $vehicle_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property ReceptionCategory $reception_category
 * @property VehicleUserType $vehicle_user_type
 * @property BodyType $body_type
 * @property int $seats_count
 * @property EnergySource $energy_source
 * @property UnderlyingCombustionEngineType|null $underlying_combustion_engine_type
 * @property EuroStandard|null $euro_standard
 * @property PollutantCategory $pollutant_category
 * @property HomologationMethod $homologation_method
 * @property int|null $co2_wltp
 * @property int|null $co2_nedc
 * @property bool $accepts_e85
 * @property int|null $taxable_horsepower
 * @property int|null $kerb_mass
 * @property bool $handicap_access
 * @property bool $n1_passenger_transport
 * @property bool $n1_removable_second_row_seat
 * @property bool $m1_special_use
 * @property bool $n1_ski_lift_use
 * @property FiscalCharacteristicsChangeReason $change_reason
 * @property string|null $change_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'vehicle_id',
    'effective_from',
    'effective_to',
    'reception_category',
    'vehicle_user_type',
    'body_type',
    'seats_count',
    'energy_source',
    'underlying_combustion_engine_type',
    'euro_standard',
    'pollutant_category',
    'homologation_method',
    'co2_wltp',
    'co2_nedc',
    'accepts_e85',
    'taxable_horsepower',
    'kerb_mass',
    'handicap_access',
    'n1_passenger_transport',
    'n1_removable_second_row_seat',
    'm1_special_use',
    'n1_ski_lift_use',
    'change_reason',
    'change_note',
])]
#[ObservedBy([VehicleFiscalCharacteristicsObserver::class])]
final class VehicleFiscalCharacteristics extends Model
{
    /** @use HasFactory<VehicleFiscalCharacteristicsFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'reception_category' => ReceptionCategory::class,
            'vehicle_user_type' => VehicleUserType::class,
            'body_type' => BodyType::class,
            'energy_source' => EnergySource::class,
            'underlying_combustion_engine_type' => UnderlyingCombustionEngineType::class,
            'euro_standard' => EuroStandard::class,
            'pollutant_category' => PollutantCategory::class,
            'homologation_method' => HomologationMethod::class,
            'change_reason' => FiscalCharacteristicsChangeReason::class,
            'accepts_e85' => 'boolean',
            'handicap_access' => 'boolean',
            'n1_passenger_transport' => 'boolean',
            'n1_removable_second_row_seat' => 'boolean',
            'm1_special_use' => 'boolean',
            'n1_ski_lift_use' => 'boolean',
        ];
    }

    /**
     * Owning vehicle.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Whether this version is the current one (`effective_to IS NULL`).
     * Mirrors the SQL-side virtual `is_current` column used by the partial index emulation.
     */
    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }
}
