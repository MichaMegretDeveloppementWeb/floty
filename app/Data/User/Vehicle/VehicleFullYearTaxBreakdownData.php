<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail du calcul du « Coût plein {année} » d'un véhicule — exposé
 * par segment VFC depuis le chantier dette VFC pour garantir la
 * cohérence affichage/total quand un véhicule a plusieurs versions
 * actives sur l'année.
 *
 * Champs agrégés au niveau année :
 *   - total : somme des `co2Due + pollutantsDue` de tous les segments,
 *     arrondie selon R-2024-003.
 *   - appliedExemptions / appliedRuleCodes / appliedRules : union
 *     dédupliquée par code de règle sur tous les segments.
 *
 * Champ par segment :
 *   - taxSegments : un {@see VehicleFullYearTaxSegmentData} par
 *     période VFC active. Liste à 1 segment en mono-VFC, à N en
 *     multi-VFC. Vide ssi le véhicule n'avait pas de VFC sur l'année
 *     calculée (cas véhicule créé après l'exercice — service
 *     responsable de poser un placeholder explicite).
 */
#[TypeScript]
final class VehicleFullYearTaxBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     * @param  list<FiscalRuleListItemData>  $appliedRules
     * @param  list<VehicleFullYearTaxSegmentData>  $taxSegments
     */
    public function __construct(
        public int $daysInYear,
        public float $total,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
        #[DataCollectionOf(FiscalRuleListItemData::class)]
        public array $appliedRules,
        #[DataCollectionOf(VehicleFullYearTaxSegmentData::class)]
        public array $taxSegments,
    ) {}
}
