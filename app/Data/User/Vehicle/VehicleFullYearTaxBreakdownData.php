<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail du calcul du « Coût plein {année} » d'un véhicule — affiché
 * dans la sidebar de la page Show pour expliquer comment le total a
 * été obtenu.
 *
 *   - co2Method               : méthode d'aiguillage CO₂ (WLTP/NEDC/PA)
 *   - co2FullYearTariff       : tarif annuel CO₂ avant prorata, après
 *                               application des règles d'aiguillage
 *   - pollutantCategory       : catégorie polluants déterminée
 *   - pollutantsFullYearTariff : tarif annuel polluants avant prorata
 *   - exemptionReasons        : raisons textuelles des exonérations
 *                               appliquées au calcul plein année
 *                               (ex. véhicule électrique → CO₂ = 0)
 *   - appliedRuleCodes        : codes des règles fiscales appliquées
 *                               (R-2024-XXX) — utile pour traçabilité
 *   - total                   : `co2FullYearTariff +
 *                               pollutantsFullYearTariff` arrondi
 */
#[TypeScript]
final class VehicleFullYearTaxBreakdownData extends Data
{
    /**
     * @param  list<string>  $exemptionReasons
     * @param  list<string>  $appliedRuleCodes
     */
    public function __construct(
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public array $exemptionReasons,
        public array $appliedRuleCodes,
        public float $total,
    ) {}
}
