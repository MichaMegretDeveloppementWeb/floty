<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\BodyType;
use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\EuroStandard;
use App\Enums\Vehicle\FiscalCharacteristicsChangeReason;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use App\Enums\Vehicle\ReceptionCategory;
use App\Enums\Vehicle\UnderlyingCombustionEngineType;
use App\Enums\Vehicle\VehicleUserType;
use App\Models\VehicleFiscalCharacteristics;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation d'une période fiscale d'un véhicule (1 ligne de
 * `vehicle_fiscal_characteristics`).
 *
 * Utilisé dans la page Show pour afficher la version courante et
 * l'historique. Les champs étendus (n1_*, m1_*, exempted activity,
 * underlying engine type, kerb mass) sont exposés en lecture seule -
 * non éditables via le formulaire Create/Edit (cf. mémoire architecture
 * - décision client : restent à null/false par défaut, prévus pour
 * usage futur).
 */
#[TypeScript]
final class VehicleFiscalCharacteristicsData extends Data
{
    public function __construct(
        public int $id,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public bool $isCurrent,
        public ReceptionCategory $receptionCategory,
        public VehicleUserType $vehicleUserType,
        public BodyType $bodyType,
        public int $seatsCount,
        public EnergySource $energySource,
        public ?UnderlyingCombustionEngineType $underlyingCombustionEngineType,
        public ?EuroStandard $euroStandard,
        public PollutantCategory $pollutantCategory,
        public HomologationMethod $homologationMethod,
        public ?int $co2Wltp,
        public ?int $co2Nedc,
        public ?int $taxableHorsepower,
        public ?int $kerbMass,
        public bool $handicapAccess,
        public bool $n1PassengerTransport,
        public bool $n1RemovableSecondRowSeat,
        public bool $m1SpecialUse,
        public bool $n1SkiLiftUse,
        public FiscalCharacteristicsChangeReason $changeReason,
        public ?string $changeNote,
    ) {}

    public static function fromModel(VehicleFiscalCharacteristics $vfc): self
    {
        return new self(
            id: $vfc->id,
            effectiveFrom: $vfc->effective_from->toDateString(),
            effectiveTo: $vfc->effective_to?->toDateString(),
            isCurrent: $vfc->isCurrent(),
            receptionCategory: $vfc->reception_category,
            vehicleUserType: $vfc->vehicle_user_type,
            bodyType: $vfc->body_type,
            seatsCount: $vfc->seats_count,
            energySource: $vfc->energy_source,
            underlyingCombustionEngineType: $vfc->underlying_combustion_engine_type,
            euroStandard: $vfc->euro_standard,
            pollutantCategory: $vfc->pollutant_category,
            homologationMethod: $vfc->homologation_method,
            co2Wltp: $vfc->co2_wltp,
            co2Nedc: $vfc->co2_nedc,
            taxableHorsepower: $vfc->taxable_horsepower,
            kerbMass: $vfc->kerb_mass,
            handicapAccess: $vfc->handicap_access,
            n1PassengerTransport: $vfc->n1_passenger_transport,
            n1RemovableSecondRowSeat: $vfc->n1_removable_second_row_seat,
            m1SpecialUse: $vfc->m1_special_use,
            n1SkiLiftUse: $vfc->n1_ski_lift_use,
            changeReason: $vfc->change_reason,
            changeNote: $vfc->change_note,
        );
    }
}
