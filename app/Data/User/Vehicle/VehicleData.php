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
 * Full representation of a vehicle for the Show page: identity, active VFC,
 * historical VFC list, usage stats, unavailabilities, busy dates and pricings.
 *
 * `currentFiscalCharacteristics` is nullable for robustness, but a vehicle
 * created via {@see CreateVehicleAction} always has an initial VFC row.
 * The historical multi-year `history` prop is served separately via
 * `Inertia::defer` and is not part of this DTO.
 */
#[TypeScript]
final class VehicleData extends Data
{
    /**
     * @param  list<VehicleFiscalCharacteristicsData>  $fiscalCharacteristicsHistory
     * @param  list<UnavailabilityData>  $unavailabilities
     * @param  list<string>  $busyDates  Dates ISO Y-m-d where the vehicle is assigned on the active year.
     * @param  list<VehicleYearlyPricingData>  $yearlyPricings  Rental rates per year, ascending order.
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
        public int $kpiYear,
        public VehicleYearStatsData $kpiStats,
        public bool $kpiFiscalAvailable,
        public int $selectedYear,
        public YearScopeData $yearScope,
        #[DataCollectionOf(VehicleYearlyPricingData::class)]
        public array $yearlyPricings,
    ) {}

    /**
     * Hydrate from a Vehicle loaded with its full fiscal history and a
     * pre-computed usage aggregate for the active year.
     *
     * @param  list<UnavailabilityData>  $unavailabilities
     * @param  list<string>  $busyDates
     */
    public static function fromModel(
        Vehicle $vehicle,
        VehicleUsageStatsData $usageStats,
        array $unavailabilities,
        array $busyDates,
        int $kpiYear,
        VehicleYearStatsData $kpiStats,
        bool $kpiFiscalAvailable,
        int $selectedYear,
        YearScopeData $yearScope,
    ): self {
        $fiscalHistory = $vehicle->fiscalCharacteristics
            ->map(static fn ($vfc): VehicleFiscalCharacteristicsData => VehicleFiscalCharacteristicsData::fromModel($vfc))
            ->values()
            ->all();

        $current = $vehicle->fiscalCharacteristics
            ->firstWhere(static fn ($vfc): bool => $vfc->effective_to === null);

        $yearlyPricings = $vehicle->yearlyPricings
            ->map(static fn ($pricing): VehicleYearlyPricingData => VehicleYearlyPricingData::fromModel($pricing))
            ->values()
            ->all();

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
            selectedYear: $selectedYear,
            yearScope: $yearScope,
            yearlyPricings: $yearlyPricings,
        );
    }
}
