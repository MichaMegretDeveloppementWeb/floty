<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
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
 * **Statut Z2** · squelette Boot vide. Les classes de règles seront
 * créées dans le Bloc Z3 (pipeline · ~21 classes) et Z4 (informatives ·
 * ~14 classes). Au seed, la table `fiscal_rules` pour l'année 2026
 * sera vide tant que `rules()` et `informativeRules()` retournent `[]`.
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
     * Catalogue prévisionnel 2026 (cf. taxes-rules/2026.md § « Synthèse Bloc Z1 ») ·
     * **21 classes pipeline** = 16 actives + 5 inactives Floty V1.
     *
     * Liste prévue (à activer au fur et à mesure de l'implémentation Z3) ·
     * - Classification (4 · incl. R-2026-013-bis) ·
     *   R2026_004_FiscalTypeQualification (cascade M1/N1 stable depuis 01/03/2025)
     *   R2026_005_Co2MethodSelection
     *   R2026_013_PollutantCategoryAssignment (v 01/01-31/08/2026)
     *   R2026_013bis_PollutantCategoryAssignment (v 01/09-31/12/2026 · Ordo 2025-1247 art. 7)
     * - Exemption (8 · incl. R-2026-018-bis) ·
     *   R2026_008_ReductiveUnavailability
     *   R2026_015_HandicapAccess
     *   R2026_016_ElectricHydrogen
     *   R2026_018_OigExemption (inactive · v 01/01-31/08/2026)
     *   R2026_018bis_OigExemption (inactive · v 01/09-31/12/2026 · Ordo 2025-1247 art. 4)
     *   R2026_019_IndividualBusinessExemption (inactive)
     *   R2026_021_ShortTermRental
     *   R2026_026_SpecificActivityExemptions (inactive)
     * - Pricing CO₂ (3 · durcissement majeur 2026 par LF 2024 art. 97-20°) ·
     *   R2026_010_WltpProgressive · WLTP 100 g/km = 213 €
     *   R2026_011_NedcProgressive
     *   R2026_012_PaProgressive
     * - Pricing polluants (2 · scission matérielle 01/03/2026 par LF 2026 art. 58) ·
     *   R2026_014_PollutantsFlat (v 01/01-28/02/2026 · tarifs 2025 100/500)
     *   R2026_014bis_PollutantsFlat (v 01/03-31/12/2026 · tarifs +30 % 130/650)
     * - Abatement (1 · reconduit) ·
     *   R2026_023_E85Abatement
     * - Transversal (3) ·
     *   R2026_002_DailyProrata (365 j)
     *   R2026_003_FinalRounding
     *   R2026_027_MileageReimbursementCoefficient (inactive)
     *
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array
    {
        return [
            // Les classes de règles 2026 seront ajoutées au Bloc Z3.
            // À ce stade (Z2 · structure code), le tableau est vide ·
            // le seeder produit 0 entrée 2026 en BDD.
        ];
    }

    /**
     * Catalogue prévisionnel 2026 ·
     * **14 classes documentaires-only** (informatives).
     *
     * Liste prévue (à créer au Bloc Z4) ·
     * - R2026_001_TaxpayerAndTriggeringEvent (1 seule version 2026 · L. 421-94/95 stable depuis 01/03/2025)
     * - R2026_006_PaFallback
     * - R2026_007_VehicleCharacteristicsHistorization
     * - R2026_009_MidYearDecommissioning
     * - R2026_020_RenterExemption (URL L. 421-140 = LEGIARTI000044602921 · bug fix verrouillé)
     * - R2026_022_ContractualPeriodVsEffectiveUsage
     * - R2026_024_CritAirGuard
     * - R2026_025_WeightedAverageTariff
     * - R2026_028_DeclarationModalities (1 seule version 2026)
     * - R2026_029_RegistrationCo2Malus (taxe connexe inactive · seuil 108 g/km, plafond 80K€ LF 2026)
     * - R2026_030_RegistrationWeightMalus (inactive · seuil 1500 kg LF 2026)
     * - R2026_031_RegistrationCardTaxes (inactive · +14 € IDF mars 2026 LF 2026)
     * - R2026_032_HeavyVehiclesTax (inactive · hors champ M1/N1)
     * - R2026_033_FleetGreeningIncentiveTax (TAI régime plein 2026 · tarif 4 000 €, quota 18 % · inactive Floty V1)
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [
            // Les classes documentaires-only 2026 seront ajoutées au Bloc Z4.
        ];
    }
}
