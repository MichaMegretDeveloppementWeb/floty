<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Data\Shared\YearScopeData;
use App\Data\User\Unavailability\UnavailabilityData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation complète d'un véhicule pour la page Show - identité +
 * caractéristiques fiscales actives + historique antéchronologique des
 * versions VFC.
 *
 * `currentFiscalCharacteristics` est typé nullable par robustesse mais
 * un véhicule créé via {@see CreateVehicleAction}
 * a toujours au moins une version initiale (`change_reason =
 * InitialCreation`).
 */
#[TypeScript]
final class VehicleData extends Data
{
    /**
     * @param  list<VehicleFiscalCharacteristicsData>  $fiscalCharacteristicsHistory
     * @param  list<UnavailabilityData>  $unavailabilities
     * @param  list<string>  $busyDates  Dates ISO Y-m-d où le véhicule
     *                                   est attribué sur l'année active
     *                                   (alimente le DateRangePicker
     *                                   du modal indispos).
     * @param  list<VehicleYearStatsData>  $history  Stats annuelles passées
     *                                               `[minYear..kpiYear-1]`,
     *                                               lignes neutres (zéros)
     *                                               comprises (cf. doctrine
     *                                               temporelle Phase 2).
     * @param  list<int>  $explorableYears  Sous-ensemble du scope global
     *                                      restreint aux années dont les
     *                                      règles fiscales sont codées
     *                                      (`scope ∩ registry`). Alimente
     *                                      le sélecteur d'année partagé
     *                                      Timeline/Breakdown/FullYearTax.
     */
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public ?string $vin,
        public ?string $color,
        public ?string $photoPath,
        public string $firstFrenchRegistrationDate,
        public string $firstOriginRegistrationDate,
        public string $firstEconomicUseDate,
        public string $acquisitionDate,
        public ?string $exitDate,
        public ?VehicleExitReason $exitReason,
        public bool $isExited,
        public VehicleStatus $currentStatus,
        public ?int $mileageCurrent,
        public ?string $notes,
        public ?VehicleFiscalCharacteristicsData $currentFiscalCharacteristics,
        #[DataCollectionOf(VehicleFiscalCharacteristicsData::class)]
        public array $fiscalCharacteristicsHistory,
        public VehicleUsageStatsData $usageStats,
        #[DataCollectionOf(UnavailabilityData::class)]
        public array $unavailabilities,
        public array $busyDates,
        // Doctrine temporelle (chantier η Phase 2) — 3 lentilles distinctes :
        public int $kpiYear,
        public VehicleYearStatsData $kpiStats,
        public bool $kpiFiscalAvailable,
        #[DataCollectionOf(VehicleYearStatsData::class)]
        public array $history,
        public int $selectedYear,
        public YearScopeData $yearScope,
        public array $explorableYears,
    ) {}

    /**
     * Compose le DTO depuis un Vehicle déjà chargé avec son historique
     * fiscal (cf. `VehicleReadRepository::findByIdWithFiscalHistory`)
     * et un agrégat statistiques pré-calculé pour l'année active.
     *
     * Le tri antéchronologique de l'historique est garanti par le repo
     * (ORDER BY effective_from DESC). La version courante est extraite
     * depuis cet historique, sans requête supplémentaire.
     *
     * @param  list<UnavailabilityData>  $unavailabilities
     * @param  list<string>  $busyDates
     * @param  list<VehicleYearStatsData>  $history
     * @param  list<int>  $explorableYears
     */
    public static function fromModel(
        Vehicle $vehicle,
        VehicleUsageStatsData $usageStats,
        array $unavailabilities,
        array $busyDates,
        int $kpiYear,
        VehicleYearStatsData $kpiStats,
        bool $kpiFiscalAvailable,
        array $history,
        int $selectedYear,
        YearScopeData $yearScope,
        array $explorableYears,
    ): self {
        $fiscalHistory = $vehicle->fiscalCharacteristics
            ->map(static fn ($vfc): VehicleFiscalCharacteristicsData => VehicleFiscalCharacteristicsData::fromModel($vfc))
            ->values()
            ->all();

        $current = $vehicle->fiscalCharacteristics
            ->firstWhere(static fn ($vfc): bool => $vfc->effective_to === null);

        return new self(
            id: $vehicle->id,
            licensePlate: $vehicle->license_plate,
            brand: $vehicle->brand,
            model: $vehicle->model,
            vin: $vehicle->vin,
            color: $vehicle->color,
            photoPath: $vehicle->photo_path,
            firstFrenchRegistrationDate: $vehicle->first_french_registration_date->toDateString(),
            firstOriginRegistrationDate: $vehicle->first_origin_registration_date->toDateString(),
            firstEconomicUseDate: $vehicle->first_economic_use_date->toDateString(),
            acquisitionDate: $vehicle->acquisition_date->toDateString(),
            exitDate: $vehicle->exit_date?->toDateString(),
            exitReason: $vehicle->exit_reason,
            isExited: $vehicle->is_exited,
            currentStatus: $vehicle->current_status,
            mileageCurrent: $vehicle->mileage_current,
            notes: $vehicle->notes,
            currentFiscalCharacteristics: $current !== null
                ? VehicleFiscalCharacteristicsData::fromModel($current)
                : null,
            fiscalCharacteristicsHistory: $fiscalHistory,
            usageStats: $usageStats,
            unavailabilities: $unavailabilities,
            busyDates: $busyDates,
            kpiYear: $kpiYear,
            kpiStats: $kpiStats,
            kpiFiscalAvailable: $kpiFiscalAvailable,
            history: $history,
            selectedYear: $selectedYear,
            yearScope: $yearScope,
            explorableYears: $explorableYears,
        );
    }
}
