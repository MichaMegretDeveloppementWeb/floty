<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\VehicleYearlyPricing;
use App\Services\Invoice\InvoiceDivergenceFlagger;

/**
 * Flags as divergent every invoice impacted by a change of vehicle
 * pricing for a given year. Wired through `#[ObservedBy]` on the model.
 */
final class VehicleYearlyPricingObserver
{
    public function __construct(
        private readonly InvoiceDivergenceFlagger $flagger,
    ) {}

    /**
     * Flag impacted invoices on creation of a yearly pricing row.
     */
    public function created(VehicleYearlyPricing $pricing): void
    {
        $this->flagger->flagForVehiclePricingYear($pricing->vehicle_id, $pricing->year);
    }

    /**
     * Flag impacted invoices on update of a yearly pricing row.
     */
    public function updated(VehicleYearlyPricing $pricing): void
    {
        $this->flagger->flagForVehiclePricingYear($pricing->vehicle_id, $pricing->year);
    }

    /**
     * Flag impacted invoices on deletion of a yearly pricing row.
     */
    public function deleted(VehicleYearlyPricing $pricing): void
    {
        $this->flagger->flagForVehiclePricingYear($pricing->vehicle_id, $pricing->year);
    }
}
