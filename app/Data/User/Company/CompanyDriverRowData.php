<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la section "Conducteurs" de la page Show Company (Phase 06 L4).
 *
 * Différent de `DriverListItemData` global : ici on ne montre que la
 * membership avec cette company, pas toutes les memberships du driver.
 */
#[TypeScript]
final class CompanyDriverRowData extends Data
{
    public function __construct(
        public int $driverId,
        public int $pivotId,
        public string $fullName,
        public string $initials,
        public string $joinedAt,
        public ?string $leftAt,
        public bool $isCurrentlyActive,
        public int $contractsCount,
    ) {}
}
