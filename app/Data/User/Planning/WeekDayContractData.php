<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Data\User\Company\CompanyOptionData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail d'un contrat couvrant un jour donné dans la grille semaine.
 */
#[TypeScript]
final class WeekDayContractData extends Data
{
    public function __construct(
        public int $id,
        public CompanyOptionData $company,
    ) {}
}
