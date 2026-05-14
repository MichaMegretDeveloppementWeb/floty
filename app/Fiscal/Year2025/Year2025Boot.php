<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Fiscal\Year2025\Classification\R2025_004_FiscalTypeQualification;
use App\Fiscal\Year2025\Classification\R2025_005_Co2MethodSelection;
use App\Fiscal\Year2025\Classification\R2025_006_PaFallback;
use App\Fiscal\Year2025\Classification\R2025_013_PollutantCategoryAssignment;
use App\Fiscal\Year2025\Exemption\R2025_008_ReductiveUnavailability;
use App\Fiscal\Year2025\Exemption\R2025_015_HandicapAccess;
use App\Fiscal\Year2025\Exemption\R2025_016_ElectricHydrogen;
use App\Fiscal\Year2025\Exemption\R2025_018_OigExemption;
use App\Fiscal\Year2025\Exemption\R2025_019_IndividualBusinessExemption;
use App\Fiscal\Year2025\Exemption\R2025_020_RenterExemption;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_026_SpecificActivityExemptions;
use App\Fiscal\Year2025\Pricing\R2025_010_WltpProgressive;
use App\Fiscal\Year2025\Pricing\R2025_011_NedcProgressive;
use App\Fiscal\Year2025\Pricing\R2025_012_PaProgressive;
use App\Fiscal\Year2025\Pricing\R2025_014_PollutantsFlat;
use App\Fiscal\Year2025\Transversal\R2025_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2025\Transversal\R2025_002_DailyProrata;
use App\Fiscal\Year2025\Transversal\R2025_003_FinalRounding;
use App\Fiscal\Year2025\Transversal\R2025_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2025\Transversal\R2025_009_MidYearDecommissioning;
use App\Fiscal\Year2025\Transversal\R2025_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2025\Transversal\R2025_024_CritAirGuard;
use App\Fiscal\Year2025\Transversal\R2025_025_WeightedAverageTariff;
use App\Fiscal\Year2025\Transversal\R2025_027_MileageReimbursementCoefficient;
use App\Fiscal\Year2025\Transversal\R2025_028_DeclarationModalities;
use App\Fiscal\Year2025\Transversal\R2025_029_RegistrationCo2Malus;
use App\Fiscal\Year2025\Transversal\R2025_030_RegistrationWeightMalus;
use App\Fiscal\Year2025\Transversal\R2025_031_RegistrationCardTaxes;
use App\Fiscal\Year2025\Transversal\R2025_032_HeavyVehiclesTax;
use App\Fiscal\Year2025\Transversal\R2025_033_FleetGreeningIncentiveTax;
use App\Providers\FiscalServiceProvider;

/**
 * Catalogue des règles fiscales 2025 (cf. `taxes-rules/2025.md`).
 *
 * Référencée par `config('floty.fiscal.year_boots')` et instanciée par
 * {@see FiscalServiceProvider} au boot.
 *
 * **Changements majeurs vs 2024** :
 * - Dénominateur prorata = 365 (2025 non bissextile, vs 366 en 2024).
 * - Barèmes WLTP/NEDC/PA durcis par LF 2024 art. 97, 19° au 01/01/2025.
 * - Exonération hybride conditionnelle R-2024-017 supprimée au 01/01/2025.
 * - Nouveauté · abattement E85 R-2025-023 (CIBS L. 421-125 réformé).
 *
 * **Isolation stricte** · aucune classe `App\Fiscal\Year2024\*` n'est
 * référencée ni utilisée dans le pipeline 2025 (cf. ADR-0022 et la
 * section « Garantie de conformité fiscale 2025 » de
 * `taxes-rules/2025.md`).
 *
 * Pour modifier le périmètre des règles 2025 (ajout, retrait, réordonnance),
 * éditer la liste {@see rules()} ci-dessous · sans toucher au provider.
 */
final class Year2025Boot implements FiscalYearBoot
{
    public function year(): int
    {
        return 2025;
    }

    /**
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array
    {
        // Phases C-H · 3 Classification + 7 Exemption + 4 Pricing + 1
        // Abatement (E85 nouveauté 2025) + 3 Transversal = 18 classes
        // inscrites. Le décorateur R2025_021_WithOptOuts (19e fichier
        // PHP) n'est PAS inscrit ici · il est résolu runtime par
        // OverlayedRuleRegistry.
        return [
            // Classification (3)
            R2025_004_FiscalTypeQualification::class,
            R2025_005_Co2MethodSelection::class,
            R2025_013_PollutantCategoryAssignment::class,
            // Exemption (7 · R-2025-017 hybride supprimée vs 2024)
            R2025_008_ReductiveUnavailability::class,
            R2025_015_HandicapAccess::class,
            R2025_016_ElectricHydrogen::class,
            R2025_018_OigExemption::class,
            R2025_019_IndividualBusinessExemption::class,
            R2025_021_ShortTermRental::class,
            R2025_026_SpecificActivityExemptions::class,
            // Pricing CO₂ (durcissement majeur 2025)
            R2025_010_WltpProgressive::class,
            R2025_011_NedcProgressive::class,
            R2025_012_PaProgressive::class,
            // Pricing polluants (identique 2024)
            R2025_014_PollutantsFlat::class,
            // Abatement (nouveauté 2025 · E85)
            R2025_023_E85Abatement::class,
            // Transversal (3)
            R2025_002_DailyProrata::class,
            R2025_003_FinalRounding::class,
            R2025_027_MileageReimbursementCoefficient::class,
        ];
    }

    /**
     * Règles documentaires-only seedées dans `fiscal_rules` pour
     * alimenter la page « Règles de calcul » mais qui ne participent
     * **pas** au pipeline de calcul (cf. {@see InformativeRule}).
     *
     * **Composition 14 classes 2025** · 7 reconduites de 2024 (cadre
     * architectural et garde-fous) + 7 ajouts du chantier 14/05/2026
     * (moyenne pondérée + modalités déclaratives + 5 taxes connexes
     * hors périmètre Floty, dont la **TAI nouveauté 2025**).
     *
     * **Spécificités 2025 vs 2024** :
     * - R-2024-023 (abattement vide) disparaît · slot remplacé en
     *   pipeline par R-2025-023 (E85 actif).
     * - R-2025-029 (malus CO₂) durci au 01/03/2025.
     * - R-2025-031 (taxes carte grise) · évolution 01/05/2025.
     * - R-2025-033 (TAI verdissement flottes) · nouveauté 2025 sans
     *   équivalent 2024.
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [
            R2025_001_TaxpayerAndTriggeringEvent::class,
            R2025_006_PaFallback::class,
            R2025_007_VehicleCharacteristicsHistorization::class,
            R2025_009_MidYearDecommissioning::class,
            R2025_020_RenterExemption::class,
            R2025_022_ContractualPeriodVsEffectiveUsage::class,
            R2025_024_CritAirGuard::class,
            // Ajouts audit exhaustif 14/05/2026
            R2025_025_WeightedAverageTariff::class,
            R2025_028_DeclarationModalities::class,
            R2025_029_RegistrationCo2Malus::class,
            R2025_030_RegistrationWeightMalus::class,
            R2025_031_RegistrationCardTaxes::class,
            R2025_032_HeavyVehiclesTax::class,
            // Nouveauté 2025 · pas d'équivalent 2024
            R2025_033_FleetGreeningIncentiveTax::class,
        ];
    }
}
