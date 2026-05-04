<?php

declare(strict_types=1);

namespace App\Data\User\Driver;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Représente une membership Driver↔Company (1 ligne pivot `driver_company`).
 *
 * Cf. Phase 06 V1.2 - un driver peut appartenir à plusieurs entreprises au
 * cours du temps, chaque membership porte ses propres dates d'entrée/sortie.
 */
#[TypeScript]
final class DriverCompanyMembershipData extends Data
{
    public function __construct(
        public int $pivotId,
        public int $companyId,
        public string $companyShortCode,
        public string $companyLegalName,
        public CompanyColor $companyColor,
        public string $joinedAt,
        public ?string $leftAt,
        public bool $isCurrentlyActive,
        public int $contractsCount,
    ) {}
}
