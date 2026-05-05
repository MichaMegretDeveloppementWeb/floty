<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail tarifaire d'un segment VFC dans le calcul du Coût plein
 * d'un véhicule sur une année (chantier dette VFC).
 *
 * Quand un véhicule a une seule VFC sur l'année → un unique segment
 * couvrant `2024-01-01 → 2024-12-31`, `co2Due = co2FullYearTariff`
 * (prorata 1.0). Quand la VFC change en cours d'année → un segment
 * par période, `co2Due = co2FullYearTariff × jours_segment / jours_année`.
 * La somme des `co2Due + pollutantsDue` de chaque segment donne le
 * total annuel cohérent.
 *
 *   - effectiveFromInYear / effectiveToInYear : bornes inclusives du
 *     segment, déjà clippées à l'année calculée.
 *   - daysInSegment : nombre de jours du segment (utile pour expliquer
 *     le prorata appliqué).
 *   - vfc : la version VFC active sur ce segment.
 *   - co2FullYearTariff / pollutantsFullYearTariff : tarif annuel
 *     théorique de la règle pricing pour cette VFC (avant prorata).
 *   - co2Due / pollutantsDue : montant effectivement dû pour le segment
 *     (= tarif × prorata segment).
 *   - co2Explanation / pollutantsExplanation : phrase explicative de
 *     l'aiguillage pricing (ex. « 145 g/km (WLTP) × barème CO₂ 2024
 *     → tarif annuel 181,00 € »).
 *   - appliedExemptions / appliedRuleCodes : exonérations et règles
 *     appliquées spécifiquement à ce segment (peuvent différer entre
 *     segments si la VFC change de catégorie polluants ou d'énergie).
 */
#[TypeScript]
final class VehicleFullYearTaxSegmentData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     */
    public function __construct(
        public string $effectiveFromInYear,
        public string $effectiveToInYear,
        public int $daysInSegment,
        public VehicleFiscalCharacteristicsData $vfc,
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public string $co2Explanation,
        public float $co2Due,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public string $pollutantsExplanation,
        public float $pollutantsDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
    ) {}
}
