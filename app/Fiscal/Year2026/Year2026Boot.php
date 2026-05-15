<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2026\Abatement\R2026_023_E85Abatement;
use App\Fiscal\Year2026\Classification\R2026_004_FiscalTypeQualification;
use App\Fiscal\Year2026\Classification\R2026_005_Co2MethodSelection;
use App\Fiscal\Year2026\Classification\R2026_013_PollutantCategoryAssignment;
use App\Fiscal\Year2026\Classification\R2026_013bis_PollutantCategoryAssignment;
use App\Fiscal\Year2026\Exemption\R2026_008_ReductiveUnavailability;
use App\Fiscal\Year2026\Exemption\R2026_015_HandicapAccess;
use App\Fiscal\Year2026\Exemption\R2026_016_ElectricHydrogen;
use App\Fiscal\Year2026\Exemption\R2026_018_OigExemption;
use App\Fiscal\Year2026\Exemption\R2026_018bis_OigExemption;
use App\Fiscal\Year2026\Exemption\R2026_019_IndividualBusinessExemption;
use App\Fiscal\Year2026\Exemption\R2026_021_ShortTermRental;
use App\Fiscal\Year2026\Exemption\R2026_026_SpecificActivityExemptions;
use App\Fiscal\Year2026\Pricing\R2026_010_WltpProgressive;
use App\Fiscal\Year2026\Pricing\R2026_011_NedcProgressive;
use App\Fiscal\Year2026\Pricing\R2026_012_PaProgressive;
use App\Fiscal\Year2026\Pricing\R2026_014_PollutantsFlat;
use App\Fiscal\Year2026\Pricing\R2026_014bis_PollutantsFlat;
use App\Fiscal\Year2026\Transversal\R2026_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2026\Transversal\R2026_002_DailyProrata;
use App\Fiscal\Year2026\Transversal\R2026_003_FinalRounding;
use App\Fiscal\Year2026\Transversal\R2026_025_WeightedAverageTariff;
use App\Fiscal\Year2026\Transversal\R2026_027_MileageReimbursementCoefficient;
use App\Fiscal\Year2026\Transversal\R2026_028_DeclarationModalities;
use App\Providers\FiscalServiceProvider;

/**
 * Catalogue des règles fiscales 2026 (cf. `taxes-rules/2026.md`).
 *
 * Référencée par `config('floty.fiscal.year_boots')` et instanciée par
 * {@see FiscalServiceProvider} au boot.
 *
 * **Changements majeurs vs 2025** :
 * - Barèmes CO₂ WLTP/NEDC/PA **durcis** au 01/01/2026 par anticipation
 *   programmée de LF 2024 art. 97-20° (loi n° 2023-1322 du 29/12/2023).
 *   Exemple BOFiP officiel · WLTP 100 g/km = **213 €** plein 2026
 *   (vs 193 € en 2025, +10,4 %).
 * - **Revalorisation polluants au 01/03/2026** · LF 2026 art. 58 (V), IV
 *   (loi n° 2026-103 du 19/02/2026) · Cat1 100 → 130 €, MostPolluting
 *   500 → 650 € (+30 %). E inchangé. **Scission ADR-0022 obligatoire**.
 * - **Toilettage rédactionnel au 01/09/2026** · Ordonnance n° 2025-1247
 *   du 17/12/2025 (art. 4 + 7 + 49) · 3 articles touchés ·
 *   L. 421-126 (OIG CO₂, art. 4 · refonte CGI 261 → CIBS L. 213-XXX),
 *   L. 421-134 (catégorisation polluants, art. 7 · suppression
 *   « dans sa rédaction en vigueur »),
 *   L. 421-138 (OIG polluants, art. 4 · idem L. 421-126).
 * - **Scissions ADR-0022 strict 2026** · 3 paires bis ·
 *   - R-2026-013 / 013-bis · catégorisation polluants (rédactionnelle 01/09)
 *   - R-2026-014 / 014-bis · tarif polluants (matérielle 01/03)
 *   - R-2026-018 / 018-bis · OIG (rédactionnelle 01/09, inactive Floty V1)
 * - Abattement E85 (R-2026-023) reconduit strictement de 2025.
 * - Année non bissextile · dénominateur prorata 365 j (identique 2025).
 *
 * **Isolation stricte** · aucune classe `App\Fiscal\Year{2024,2025}\*`
 * n'est référencée ni utilisée dans le pipeline 2026 (cf. ADR-0022 et la
 * section « Garantie de conformité fiscale 2026 » de
 * `taxes-rules/2026.md`).
 *
 * **Statut Z3** · 21 classes pipeline câblées (16 actives + 5 inactives
 * Floty V1). Les 14 classes documentaires-only seront ajoutées au Bloc
 * Z4. Décorateur runtime R-2026-021-WithOptOuts substitué côté
 * `OverlayedRuleRegistry` via `FiscalServiceProvider`.
 *
 * Pour modifier le périmètre des règles 2026 (ajout, retrait,
 * réordonnance), éditer la liste {@see rules()} ou
 * {@see informativeRules()} ci-dessous · sans toucher au provider.
 */
final class Year2026Boot implements FiscalYearBoot
{
    public function year(): int
    {
        return 2026;
    }

    /**
     * **21 classes pipeline** = 16 actives + 5 inactives Floty V1.
     *
     * Décomposition · 4 Classification (incl. R-2026-013-bis) +
     * 8 Exemption (incl. R-2026-018-bis) + 3 Pricing CO₂ +
     * 2 Pricing polluants (incl. R-2026-014-bis) + 1 Abatement +
     * 3 Transversal.
     *
     * **Ordre du tableau** · catégoriel (lisibilité Boot et seeding).
     * **Ordre d'exécution** · déterminé par l'orchestrateur pipeline
     * via `ruleType()` · Classification → Exemption → Abatement →
     * Pricing → Transversal. Le tableau ci-dessous ne pilote PAS l'ordre
     * d'exécution.
     *
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array
    {
        return [
            // Classification (4 · R-2026-013 scindée en 2 versions
            // ADR-0022 · L. 421-134 toiletté 01/09/2026 par Ordo
            // 2025-1247 art. 7 · rédactionnel).
            R2026_004_FiscalTypeQualification::class,
            R2026_005_Co2MethodSelection::class,
            R2026_013_PollutantCategoryAssignment::class, // v 01/01-31/08
            R2026_013bis_PollutantCategoryAssignment::class, // v 01/09-31/12
            // Exemption (8 · R-2026-018 scindée en 2 versions ADR-0022 ·
            // L. 421-138 toiletté 01/09/2026 par Ordo 2025-1247 art. 4 ·
            // rédactionnel · inactives Floty V1).
            R2026_008_ReductiveUnavailability::class,
            R2026_015_HandicapAccess::class,
            R2026_016_ElectricHydrogen::class,
            R2026_018_OigExemption::class, // v 01/01-31/08 · inactive
            R2026_018bis_OigExemption::class, // v 01/09-31/12 · inactive
            R2026_019_IndividualBusinessExemption::class, // inactive
            R2026_021_ShortTermRental::class,
            R2026_026_SpecificActivityExemptions::class, // inactive
            // Pricing CO₂ (3 · durcissement majeur 2026 par LF 2024
            // art. 97-20°).
            R2026_010_WltpProgressive::class, // WLTP 100 g/km = 213 €
            R2026_011_NedcProgressive::class,
            R2026_012_PaProgressive::class,
            // Pricing polluants (2 · R-2026-014 scindée en 2 versions
            // ADR-0022 · L. 421-135 revalorisé +30 % au 01/03/2026 par
            // LF 2026 art. 58 (V), IV · matériel).
            R2026_014_PollutantsFlat::class, // v 01/01-28/02 · tarifs 2025
            R2026_014bis_PollutantsFlat::class, // v 01/03-31/12 · tarifs +30 %
            // Abatement (1 · reconduit de 2025 sans modification matérielle)
            R2026_023_E85Abatement::class,
            // Transversal (3 · R-2026-027 inactive Floty V1)
            R2026_002_DailyProrata::class,
            R2026_003_FinalRounding::class,
            R2026_027_MileageReimbursementCoefficient::class, // inactive
        ];
    }

    /**
     * **14 classes documentaires-only** (informatives) au catalogue 2026.
     *
     * **Composition** · 3 cadre architectural (Z4.1) + 6 garde-fous &
     * cadre interne (Z4.2) + 5 taxes connexes inactives (Z4.3).
     *
     * **Spécificités 2026 vs 2025** ·
     * - **Pas de R-2026-001-bis** · L. 421-94/95/98/99 stables depuis
     *   01/03/2025 (LF 2025 art. 28), non modifiés en 2026.
     * - **Pas de R-2026-028-bis** · L. 421-159/162/163/164/165 stables
     *   depuis 01/03/2025, non modifiés en 2026.
     * - **Pas de R-2026-029-bis** · LF 2025 art. 28 durcissement
     *   permanent du malus CO₂ acquis dès 01/01 (continuité 2025→2026).
     * - **Pas de R-2026-031-bis** · évolution +14 € IDF mars 2026 portée
     *   par une version unique (à confirmer par audit Chrome live Z4.3).
     *
     * **Statut Z4.1** · 3 classes cadre architectural câblées (1 / 28 / 25).
     * Z4.2 (6 garde-fous) et Z4.3 (5 taxes connexes) à venir.
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [
            // Cadre architectural (3 · Z4.1 · single-version 2026)
            R2026_001_TaxpayerAndTriggeringEvent::class,
            R2026_025_WeightedAverageTariff::class,
            R2026_028_DeclarationModalities::class,
            // Garde-fous & cadre interne (6 · Z4.2 · à venir)
            // - R2026_006_PaFallback
            // - R2026_007_VehicleCharacteristicsHistorization
            // - R2026_009_MidYearDecommissioning
            // - R2026_020_RenterExemption (URL L. 421-140 = LEGIARTI000044602921 verrouillée)
            // - R2026_022_ContractualPeriodVsEffectiveUsage
            // - R2026_024_CritAirGuard
            // Taxes connexes inactives (5 · Z4.3 · à venir)
            // - R2026_029_RegistrationCo2Malus
            // - R2026_030_RegistrationWeightMalus
            // - R2026_031_RegistrationCardTaxes
            // - R2026_032_HeavyVehiclesTax
            // - R2026_033_FleetGreeningIncentiveTax
        ];
    }
}
