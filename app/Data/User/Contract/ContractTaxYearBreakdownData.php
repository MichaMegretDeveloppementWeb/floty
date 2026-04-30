<?php

declare(strict_types=1);

namespace App\Data\User\Contract;

use App\Data\User\Fiscal\AppliedExemptionData;
use App\Data\User\Fiscal\FiscalRuleListItemData;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\PollutantCategory;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail fiscal d'un contrat **pour une année traversée**.
 *
 * Un contrat peut chevaucher 2 années civiles (ex. 1er nov 2024 → 31 jan
 * 2025). Le moteur fiscal tourne par année — chaque exécution produit
 * une instance de ce DTO. L'agrégat parent {@see ContractTaxBreakdownData}
 * porte la liste des années et le total cross-year.
 *
 * - `daysInContractInYear` : jours du contrat tombant dans l'année (avant
 *   exonération) — utile pour expliquer « X jours dans l'année ».
 * - `daysAssigned` : jours retenus au numérateur du prorata, après
 *   application des règles d'exonération journalière (R-2024-021 LCD,
 *   R-2024-008 indispos réductrices). C'est ce nombre qui multiplie
 *   le tarif annuel.
 * - `daysInYear` : dénominateur (366 en 2024 bissextile).
 * - `appliedRules` : détail complet (nom, description, refs légales) des
 *   règles listées dans `appliedRuleCodes` — permet d'ouvrir la fiche
 *   d'une règle directement depuis le panel sans aller-retour serveur.
 */
#[TypeScript]
final class ContractTaxYearBreakdownData extends Data
{
    /**
     * @param  list<AppliedExemptionData>  $appliedExemptions
     * @param  list<string>  $appliedRuleCodes
     * @param  list<FiscalRuleListItemData>  $appliedRules
     */
    public function __construct(
        public int $year,
        public int $daysInContractInYear,
        public int $daysAssigned,
        public int $daysInYear,
        public HomologationMethod $co2Method,
        public PollutantCategory $pollutantCategory,
        public float $co2FullYearTariff,
        public float $pollutantsFullYearTariff,
        public float $co2Due,
        public float $pollutantsDue,
        public float $totalDue,
        #[DataCollectionOf(AppliedExemptionData::class)]
        public array $appliedExemptions,
        public array $appliedRuleCodes,
        #[DataCollectionOf(FiscalRuleListItemData::class)]
        public array $appliedRules,
    ) {}
}
