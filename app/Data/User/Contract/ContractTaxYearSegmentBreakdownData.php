<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail tarifaire d'une **fenêtre** (segment VFC × Règles) dans le
 * calcul fiscal d'un contrat pour une année donnée.
 *
 * Une fenêtre apparaît quand un changement de règle ou de VFC scinde
 * la période contrat × année en sous-périodes tarifées différemment.
 * Exemples ·
 *   - Polluants 2026 · scission au 01/03 (LF 2026 art. 58 V IV, +30 %).
 *     Un contrat 15/01-24/04 a 2 fenêtres · 01/01-28/02 (R-2026-014,
 *     Cat1=100 €) et 01/03-31/12 (R-2026-014-bis, Cat1=130 €). Le
 *     contrat est clippé à 45 + 55 jours.
 *   - Multi-VFC · véhicule qui passe de Diesel à Hybride en cours
 *     d'année · 2 fenêtres avec tarifs polluants distincts.
 *
 * Si aucune scission ne s'applique au contrat × année, la liste contient
 * un seul segment couvrant toute la période contrat-dans-l'année.
 *
 * Champs ·
 *   - `effectiveFromInYear` / `effectiveToInYear` · bornes inclusives
 *     de la fenêtre (après intersection contrat × VFC × Règles).
 *   - `daysAssignedToContract` · jours du contrat tombant dans la
 *     fenêtre, après exonérations journalières. Sert de numérateur
 *     dans le prorata tarif × daysAssigned / daysInYear.
 *   - `co2FullYearTariff` / `pollutantsFullYearTariff` · tarif annuel
 *     applicable à la fenêtre (peut différer d'une fenêtre à l'autre).
 *   - `co2Due` / `pollutantsDue` · montant dû pour cette fenêtre
 *     uniquement (= tarif × daysAssigned / daysInYear, arrondi half-up).
 *   - `appliedRuleCodes` / `appliedExemptions` · règles et exonérations
 *     spécifiquement actives sur cette fenêtre.
 */
#[TypeScript]
final class ContractTaxYearSegmentBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     */
    public function __construct(
        public string $effectiveFromInYear,
        public string $effectiveToInYear,
        public int $daysAssignedToContract,
        public int $daysInYear,
        public HomologationMethod $co2Method,
        public PollutantCategory $pollutantCategory,
        public float $co2FullYearTariff,
        public float $pollutantsFullYearTariff,
        public float $co2Due,
        public float $pollutantsDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
    ) {}
}
