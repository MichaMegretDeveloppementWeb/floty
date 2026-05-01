<?php

declare(strict_types=1);

namespace App\Actions\Vehicle;

use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Data\User\Vehicle\ExitVehicleData;
use App\Exceptions\Vehicle\VehicleExitBlockedByConflictsException;
use App\Models\Vehicle;
use App\Services\Vehicle\VehicleExitImpactComputer;
use Illuminate\Support\Facades\DB;

/**
 * Sortie de flotte d'un véhicule (vente, destruction VHU, vol non
 * résolu, transfert, autre raison définitive).
 *
 * Sous transaction :
 *   1. Calcul des conflits via {@see VehicleExitImpactComputer}.
 *   2. Si au moins un contrat ou indispo déborde la date proposée →
 *      lève {@see VehicleExitBlockedByConflictsException} (l'utilisateur
 *      doit résoudre manuellement avant de retirer ; principe
 *      « pas de magie silencieuse » ADR-0018 D7).
 *   3. Sinon : pose `exit_date`, `exit_reason`, et adapte
 *      `current_status` cohérent.
 *
 * Cf. ADR-0018 § 8.1 (UX modale Sortie) et R-2024-009 amendée.
 */
final readonly class ExitVehicleAction
{
    public function __construct(
        private VehicleWriteRepositoryInterface $writer,
        private VehicleExitImpactComputer $impactComputer,
    ) {}

    public function execute(int $vehicleId, ExitVehicleData $data): Vehicle
    {
        return DB::transaction(function () use ($vehicleId, $data): Vehicle {
            $impact = $this->impactComputer->computeImpact($vehicleId, $data->exitDate);

            if ($impact->hasConflicts) {
                throw VehicleExitBlockedByConflictsException::withImpact($impact);
            }

            return $this->writer->markAsExited($vehicleId, $data);
        });
    }
}
