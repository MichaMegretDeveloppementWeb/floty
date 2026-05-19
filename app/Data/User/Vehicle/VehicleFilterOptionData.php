<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Services\Vehicle\VehicleListingService;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Minimal vehicle option for UI selectors. Carries no fiscal calculation;
 * full-year tax is fetched on demand via the dedicated AJAX endpoint.
 *
 * @see VehicleListingService::listForLightSelector()
 * @see VehicleListingService::fullYearTaxForVehicle()
 */
#[TypeScript]
final class VehicleFilterOptionData extends Data
{
    public function __construct(
        public int $id,
        public string $licensePlate,
        /** Label rendered in the dropdown ("AB-123-CD · Brand Model"). */
        public string $label,
        /** True when the vehicle has been exited (`exit_date IS NOT NULL`). */
        public bool $isExited,
        /** Exit date ISO `Y-m-d` or null. */
        public ?string $exitDate,
    ) {}
}
