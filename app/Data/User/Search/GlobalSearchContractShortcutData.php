<?php

declare(strict_types=1);

namespace App\Data\User\Search;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Shortcut to the Contracts table filtered by `(vehicle, company, year)`,
 * returned by the global search palette only when the query matches at
 * least one vehicle and one company (>= 2 tokens).
 *
 * One shortcut per starting year (`YEAR(start_date)`) to avoid landing
 * on an empty current year when the couple's contracts live elsewhere.
 *
 *  - `label`: "Renault Clio AB-123-CD · chez ACME".
 *  - `sublabel`: "5 contrats en 2024".
 *  - `href`: `/app/contracts?vehicleId=X&companyId=Y&year=2024`.
 */
#[TypeScript]
final class GlobalSearchContractShortcutData extends Data
{
    public function __construct(
        public int $vehicleId,
        public int $companyId,
        public int $year,
        public string $label,
        public string $sublabel,
        public int $count,
        public string $href,
    ) {}
}
