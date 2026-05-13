<?php

declare(strict_types=1);

namespace App\Data\User\FiscalDeclaration;

use App\Models\Contract;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représentation d'un contrat membre d'un cluster de risque
 * (Phase 11 D2). Vue de lecture seule pour l'écran de revue (D4)
 * et le PDF annexe (D5) : on expose ce qui est utile à l'arbitrage
 * humain (plage, durée dans l'année cible, intervalle inter-contrats
 * du cluster).
 */
#[TypeScript]
final class ClusterContractData extends Data
{
    public function __construct(
        public int $contractId,
        public int $vehicleId,
        public string $vehiclePlate,
        /** ISO 8601 (Y-m-d). */
        public string $startDate,
        /** ISO 8601 (Y-m-d). */
        public string $endDate,
        /** Jours du contrat dans l'année cible (peut être < durée totale si à cheval). */
        public int $durationDaysInYear,
        /** Intervalle (jours pleins) avec le contrat précédent du cluster. Null pour le 1er. */
        public ?int $intervalBeforeDays,
    ) {}

    public static function fromContract(
        Contract $contract,
        int $year,
        ?Contract $previous,
    ): self {
        $durationDaysInYear = count($contract->expandToDaysInYear($year));

        $interval = null;
        if ($previous !== null) {
            $interval = (int) $previous->end_date
                ->copy()
                ->diffInDays($contract->start_date) - 1;
        }

        return new self(
            contractId: $contract->id,
            vehicleId: $contract->vehicle_id,
            vehiclePlate: $contract->vehicle->license_plate,
            startDate: $contract->start_date->toDateString(),
            endDate: $contract->end_date->toDateString(),
            durationDaysInYear: $durationDaysInYear,
            intervalBeforeDays: $interval,
        );
    }
}
