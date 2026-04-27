<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleUserType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Version effective des caractéristiques fiscales d'un véhicule sur une
 * période (`effective_from` → `effective_to`, incluse).
 *
 * Cf. 01-schema-metier.md § 3.
 *
 * Les périodes pour un même véhicule ne se chevauchent **jamais** —
 * protection triple : service applicatif + trigger BEFORE INSERT/UPDATE +
 * verrou pessimiste (cf. § 0.3 du schema doc).
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
class VehicleFiscalCharacteristics extends Model
{
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
            'handicap_access' => 'boolean',
            'n1_passenger_transport' => 'boolean',
            'n1_removable_second_row_seat' => 'boolean',
            'm1_special_use' => 'boolean',
            'n1_ski_lift_use' => 'boolean',
        ];
    }

    /**
     * Véhicule de rattachement.
     *
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Vrai ssi cette version est la version courante (`effective_to IS NULL`).
     * Alignée sur la colonne générée virtuelle `is_current` utilisée pour
     * l'index partiel émulé côté SQL.
     */
    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }
}
