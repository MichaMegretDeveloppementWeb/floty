<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * 1 ligne du breakdown « répartition par entreprise utilisatrice »
 * affiché sur la page Show d'un véhicule pour l'année active.
 */
#[TypeScript]
final class VehicleCompanyUsageData extends Data
{
    public function __construct(
        public int $companyId,
        public string $shortCode,
        public string $legalName,
        public CompanyColor $color,
        public int $daysUsed,
        public float $taxDue,
    ) {}
}
