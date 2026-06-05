<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Actions\Vehicle\CreateVehicleAction;
use App\Data\Shared\YearScopeData;
use App\Data\User\VehicleEvent\VehicleEventData;
use App\Enums\Vehicle\VehicleExitReason;
use App\Enums\Vehicle\VehicleStatus;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleDetailService;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Slim, always-eager base for the Vehicle Show page (header on every tab
 * + Edit page): identity, active VFC, historical VFC list, vehicleEvents,
 * year context and pricings.
 *
 * The heavy overview-tab data (usage timeline, current-year KPI, busy
 * dates, history) lives in {@see VehicleOverviewData}, served only on the
 * overview tab (tab-gating). `kpiYear` stays here (Fiscal tab + events
 * timeline read it).
 *
 * `currentFiscalCharacteristics` is nullable for robustness, but a vehicle
 * created via {@see CreateVehicleAction} always has an initial VFC row.
 */
#[TypeScript]
final class VehicleData extends Data
{
    /**
     * @param  list<VehicleFiscalCharacteristicsData>  $fiscalCharacteristicsHistory
     * @param  list<VehicleEventData>  $vehicleEvents
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
        #[DataCollectionOf(VehicleEventData::class)]
        public array $vehicleEvents,
        public int $kpiYear,
        public int $selectedYear,
        public YearScopeData $yearScope,
        #[DataCollectionOf(VehicleYearlyPricingData::class)]
        public array $yearlyPricings,
    ) {}

    /**
     * Hydrate the slim base from a Vehicle loaded with its full fiscal
     * history. The overview payload (usage/KPI/busyDates/history) is built
     * separately by {@see VehicleDetailService::overviewForVehicle()}.
     *
     * @param  list<VehicleEventData>  $vehicleEvents
     */
    public static function fromModel(
        Vehicle $vehicle,
        array $vehicleEvents,
        int $kpiYear,
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
            vehicleEvents: $vehicleEvents,
            kpiYear: $kpiYear,
            selectedYear: $selectedYear,
            yearScope: $yearScope,
            yearlyPricings: $yearlyPricings,
        );
    }
}
