<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Contract\ContractType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne du tableau « Contrats à venir à résoudre » de la modal de
 * sortie d'un driver d'une entreprise (workflow Q6, chantier Leave-fix).
 *
 * `candidates` = drivers actifs dans la company sur la **période exacte
 * du contrat** (validation backend en miroir de
 * `LeaveDriverCompanyMembershipAction::validateReplacementMap`). Le
 * driver sortant est exclu de cette liste — interdit comme remplaçant
 * de lui-même.
 */
#[TypeScript]
final class FutureContractRowData extends Data
{
    /**
     * @param  list<DriverOptionData>  $candidates
     */
    public function __construct(
        public int $contractId,
        public string $vehicleLicensePlate,
        public string $startDate,
        public string $endDate,
        public int $durationDays,
        public ContractType $contractType,
        public array $candidates,
    ) {}
}
