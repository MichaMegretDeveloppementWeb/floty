<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Payload de l'onglet Fiscalité d'une entreprise pour l'année
 * sélectionnée (chantier N.2).
 *
 * `rows` : 1 ligne par véhicule utilisé.
 * `totals` : sommes par colonne du tableau, **arrondies au niveau
 *  agrégat** (R-2024-003 : un seul arrondi par redevable au sein
 *  d'un même exercice fiscal).
 * `availableYears` : plage continue `[firstYear..currentRealYear]`
 *  pour les pills de sélection rapide. Tableau vide si aucun
 *  contrat sur la company.
 */
#[TypeScript]
final class CompanyFiscalYearData extends Data
{
    /**
     * @param  list<CompanyVehicleFiscalRowData>  $rows
     * @param  list<int>  $availableYears
     */
    public function __construct(
        public int $year,
        public int $currentRealYear,
        public array $rows,
        public array $availableYears,
        public int $totalDays,
        public float $totalTaxCo2,
        public float $totalTaxPollutants,
        public float $totalTaxAll,
    ) {}
}
