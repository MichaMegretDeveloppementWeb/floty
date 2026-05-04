<?php

declare(strict_types=1);

namespace App\Data\User\Vehicle;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail du calcul du « Coût plein {année} » d'un véhicule - affiché
 * dans la sidebar de la page Show pour expliquer comment le total a
 * été obtenu.
 *
 *   - co2Method               : méthode d'aiguillage CO₂ (WLTP/NEDC/PA)
 *   - co2FullYearTariff       : tarif annuel CO₂ avant prorata, après
 *                               application des règles d'aiguillage
 *   - pollutantCategory       : catégorie polluants déterminée
 *   - pollutantsFullYearTariff : tarif annuel polluants avant prorata
 *   - appliedExemptions       : exonérations appliquées (couples
 *                               raison + code R-2024-XXX) - chaque
 *                               item est cliquable pour ouvrir la
 *                               fiche détaillée de la règle
 *   - appliedRuleCodes        : codes des règles fiscales appliquées
 *                               (R-2024-XXX) - utile pour traçabilité
 *   - total                   : `co2FullYearTariff +
 *                               pollutantsFullYearTariff` arrondi
 */
#[TypeScript]
final class VehicleFullYearTaxBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     * @param  list<FiscalRuleListItemData>  $appliedRules  Détail
     *                                                      complet (nom, description, refs légales) des règles
     *                                                      listées dans `appliedRuleCodes`. Permet d'ouvrir la
     *                                                      fiche détaillée d'une règle au clic depuis le panel
     *                                                      sans aller-retour serveur.
     */
    public function __construct(
        public HomologationMethod $co2Method,
        public float $co2FullYearTariff,
        public string $co2Explanation,
        public PollutantCategory $pollutantCategory,
        public float $pollutantsFullYearTariff,
        public string $pollutantsExplanation,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
        public float $total,
        #[DataCollectionOf(FiscalRuleListItemData::class)]
        public array $appliedRules,
    ) {}
}
