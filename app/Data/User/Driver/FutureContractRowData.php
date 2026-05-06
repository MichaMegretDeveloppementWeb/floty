<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Contract\ContractType;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne du tableau « Contrats à venir à résoudre » de la modal de
 * sortie d'un driver d'une entreprise (workflow Q6, chantier Leave-fix).
 *
 * `currentDrivers` = liste actuelle des conducteurs du contrat (pivot
 * N:N), incluant le driver sortant. Affichée dans la modale pour que
 * l'utilisateur voie le contexte (« le contrat conserve X et Y, on
 * retire Z »).
 *
 * `candidates` = drivers actifs dans la company sur la **période exacte
 * du contrat**, **hors tous les conducteurs déjà sur le contrat** (le
 * sortant compris). Validation backend en miroir de
 * `LeaveDriverCompanyMembershipAction::validateReplacementMap`.
 */
#[TypeScript]
final class FutureContractRowData extends Data
{
    /**
     * @param  list<DriverOptionData>  $currentDrivers
     * @param  list<DriverOptionData>  $candidates
     */
    public function __construct(
        public int $contractId,
        public string $vehicleLicensePlate,
        public string $startDate,
        public string $endDate,
        public int $durationDays,
        public ContractType $contractType,
        #[DataCollectionOf(DriverOptionData::class)]
        public array $currentDrivers,
        #[DataCollectionOf(DriverOptionData::class)]
        public array $candidates,
    ) {}
}
