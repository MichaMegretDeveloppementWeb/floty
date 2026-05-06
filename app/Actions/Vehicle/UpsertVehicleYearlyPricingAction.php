<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleYearlyPricingWriteRepositoryInterface;
use App\Data\User\Vehicle\VehicleYearlyPricingData;
use App\Models\VehicleYearlyPricing;
use Illuminate\Support\Facades\DB;

/**
 * Crée ou met à jour le tarif d'un véhicule pour une année donnée.
 *
 * Idempotent par construction (contrainte UNIQUE(vehicle_id, year) en base
 * + `updateOrCreate` côté repository). Wrappé dans `DB::transaction` pour
 * cohérence avec le reste des Actions du domaine Vehicle, et préparation
 * aux futures Actions composées de Phase 14.E qui devront wrapper plusieurs
 * writes (cf. `GenerateInvoiceAction`).
 */
final readonly class UpsertVehicleYearlyPricingAction
{
    public function __construct(
        private VehicleYearlyPricingWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $vehicleId, VehicleYearlyPricingData $data): VehicleYearlyPricing
    {
        return DB::transaction(fn (): VehicleYearlyPricing => $this->writer->upsert($vehicleId, $data));
    }
}
