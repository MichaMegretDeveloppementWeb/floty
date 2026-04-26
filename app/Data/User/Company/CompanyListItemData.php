<?php

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Ligne de la table « Entreprises utilisatrices » (page
 * User/Companies/Index). Inclut les agrégats annuels pour la flotte
 * (jours utilisés + taxe due).
 */
#[TypeScript]
final class CompanyListItemData extends Data
{
    public function __construct(
        public int $id,
        public string $legalName,
        public string $shortCode,
        public CompanyColor $color,
        public ?string $siren,
        public ?string $city,
        public bool $isActive,
        public int $daysUsed,
        public float $annualTaxDue,
    ) {}
}
