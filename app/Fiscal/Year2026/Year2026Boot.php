<?php

declare(strict_types=1);

namespace App\Fiscal\Year2026;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2026\Abatement\R2026_023_E85Abatement;
use App\Fiscal\Year2026\Classification\R2026_004_FiscalTypeQualification;
use App\Fiscal\Year2026\Classification\R2026_005_Co2MethodSelection;
use App\Fiscal\Year2026\Classification\R2026_006_PaFallback;
use App\Fiscal\Year2026\Classification\R2026_013_PollutantCategoryAssignment;
use App\Fiscal\Year2026\Classification\R2026_013bis_PollutantCategoryAssignment;
use App\Fiscal\Year2026\Exemption\R2026_008_ReductiveUnavailability;
use App\Fiscal\Year2026\Exemption\R2026_015_HandicapAccess;
use App\Fiscal\Year2026\Exemption\R2026_016_ElectricHydrogen;
use App\Fiscal\Year2026\Exemption\R2026_018_OigExemption;
use App\Fiscal\Year2026\Exemption\R2026_018bis_OigExemption;
use App\Fiscal\Year2026\Exemption\R2026_019_IndividualBusinessExemption;
use App\Fiscal\Year2026\Exemption\R2026_020_RenterExemption;
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
use App\Fiscal\Year2026\Transversal\R2026_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2026\Transversal\R2026_009_MidYearDecommissioning;
use App\Fiscal\Year2026\Transversal\R2026_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2026\Transversal\R2026_024_CritAirGuard;
use App\Fiscal\Year2026\Transversal\R2026_025_WeightedAverageTariff;
use App\Fiscal\Year2026\Transversal\R2026_027_MileageReimbursementCoefficient;
use App\Fiscal\Year2026\Transversal\R2026_028_DeclarationModalities;
use App\Fiscal\Year2026\Transversal\R2026_029_RegistrationCo2Malus;
use App\Fiscal\Year2026\Transversal\R2026_029bis_RegistrationCo2Malus;
use App\Fiscal\Year2026\Transversal\R2026_030_RegistrationWeightMalus;
use App\Fiscal\Year2026\Transversal\R2026_031_RegistrationCardTaxes;
use App\Fiscal\Year2026\Transversal\R2026_031bis_RegistrationCardTaxes;
use App\Fiscal\Year2026\Transversal\R2026_032_HeavyVehiclesTax;
use App\Fiscal\Year2026\Transversal\R2026_033_FleetGreeningIncentiveTax;
use App\Fiscal\Year2026\Transversal\R2026_033bis_FleetGreeningIncentiveTax;
use App\Providers\FiscalServiceProvider;

/**
 * 2026 fiscal rules catalogue (see `taxes-rules/2026.md`).
 *
 * Referenced by `config('floty.fiscal.year_boots')` and instantiated by
 * {@see FiscalServiceProvider} at boot time.
 *
 * Major changes vs 2025:
 * - CO₂ WLTP/NEDC/PA scales hardened at 01/01/2026 (LF 2024 art. 97-20°,
 *   loi n° 2023-1322 du 29/12/2023). BOFiP example: WLTP 100 g/km =
 *   213 € full 2026 (vs 193 € in 2025, +10.4%).
 * - Pollutants revaluation at 01/03/2026 (LF 2026 art. 58 (V), IV,
 *   loi n° 2026-103 du 19/02/2026): Cat1 100 → 130 €, MostPolluting
 *   500 → 650 € (+30%). E unchanged. ADR-0022 split required.
 * - Editorial cleanup at 01/09/2026 (Ordonnance n° 2025-1247 du
 *   17/12/2025, art. 4 + 7 + 49): L. 421-126 (CO₂ OIG, art. 4),
 *   L. 421-134 (pollutants categorisation, art. 7),
 *   L. 421-138 (pollutants OIG, art. 4).
 * - ADR-0022 strict 2026 splits: 3 bis pairs:
 *   - R-2026-013 / 013-bis: pollutants categorisation (editorial 01/09)
 *   - R-2026-014 / 014-bis: pollutants tariff (material 01/03)
 *   - R-2026-018 / 018-bis: OIG (editorial 01/09, inactive Floty V1)
 * - E85 abatement (R-2026-023) reproduced strictly from 2025.
 * - Non-leap year: prorata denominator 365 days (identical to 2025).
 *
 * Strict isolation: no `App\Fiscal\Year{2024,2025}\*` class is
 * referenced nor used in the 2026 pipeline (ADR-0022 and the 2026
 * conformity report).
 */
final class Year2026Boot implements FiscalYearBoot
{
    public function year(): int
    {
        return 2026;
    }

    /**
     * 21 pipeline classes = 16 active + 5 inactive Floty V1.
     *
     * Array order is categorical (Boot readability and seeding).
     * Execution order is determined by the pipeline orchestrator via
     * `ruleType()`: Classification → Exemption → Abatement → Pricing
     * → Transversal. The array below does NOT drive execution order.
     *
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array
    {
        return [
            // Classification (R-2026-013 split into 2 versions, ADR-0022,
            // L. 421-134 cleaned up 01/09/2026 by Ordo 2025-1247 art. 7,
            // editorial).
            R2026_004_FiscalTypeQualification::class,
            R2026_005_Co2MethodSelection::class,
            R2026_013_PollutantCategoryAssignment::class, // v 01/01-31/08
            R2026_013bis_PollutantCategoryAssignment::class, // v 01/09-31/12
            // Exemption (R-2026-018 split into 2 versions, ADR-0022,
            // L. 421-138 cleaned up 01/09/2026 by Ordo 2025-1247 art. 4,
            // editorial, inactive Floty V1).
            R2026_008_ReductiveUnavailability::class,
            R2026_015_HandicapAccess::class,
            R2026_016_ElectricHydrogen::class,
            R2026_018_OigExemption::class, // v 01/01-31/08 · inactive
            R2026_018bis_OigExemption::class, // v 01/09-31/12 · inactive
            R2026_019_IndividualBusinessExemption::class, // inactive
            R2026_021_ShortTermRental::class,
            R2026_026_SpecificActivityExemptions::class, // inactive
            // Pricing CO₂ (major hardening 2026 by LF 2024 art. 97-20°).
            R2026_010_WltpProgressive::class, // WLTP 100 g/km = 213 €
            R2026_011_NedcProgressive::class,
            R2026_012_PaProgressive::class,
            // Pricing pollutants (R-2026-014 split into 2 versions,
            // ADR-0022, L. 421-135 revalued +30% at 01/03/2026 by
            // LF 2026 art. 58 (V), IV, material).
            R2026_014_PollutantsFlat::class, // v 01/01-28/02 · 2025 tariffs
            R2026_014bis_PollutantsFlat::class, // v 01/03-31/12 · tariffs +30%
            // Abatement (reproduced from 2025 without material change).
            R2026_023_E85Abatement::class,
            // Transversal (R-2026-027 inactive Floty V1).
            R2026_002_DailyProrata::class,
            R2026_003_FinalRounding::class,
            R2026_027_MileageReimbursementCoefficient::class, // inactive
        ];
    }

    /**
     * 14 documentation-only (informative) classes in the 2026
     * catalogue.
     *
     * Composition: 3 architectural framework + 6 guards & internal
     * frame + 5 inactive connected taxes.
     *
     * 2026 specifics vs 2025:
     * - No R-2026-001-bis: L. 421-94/95/98/99 stable since 01/03/2025
     *   (LF 2025 art. 28), unmodified in 2026.
     * - No R-2026-028-bis: L. 421-159/162/163/164/165 stable since
     *   01/03/2025, unmodified in 2026.
     *
     * ADR-0022 strict 2026 informative splits:
     * - R-2026-029 / R-2026-029-bis: L. 421-62 v 01/01-31/08/2026 and
     *   v 01/09/2026 (Ordo 2025-1247 art. 4 + art. 49, EDITORIAL).
     * - R-2026-031 / R-2026-031-bis: L. 421-54-1 created by LF 2026
     *   art. 60 (effective 01/03/2026, MATERIAL, IDF surcharge up to
     *   +13 €).
     * - R-2026-033 / R-2026-033-bis: L. 421-132-5 v 01/03/2025-01/03/2026
     *   and v 01/03/2026 (editorial cleanup, transitional 2025 note
     *   moot).
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [
            // Architectural framework (single-version 2026).
            R2026_001_TaxpayerAndTriggeringEvent::class,
            R2026_025_WeightedAverageTariff::class,
            R2026_028_DeclarationModalities::class,
            // Guards & internal frame (reproduction of 2025).
            R2026_006_PaFallback::class,
            R2026_007_VehicleCharacteristicsHistorization::class,
            R2026_009_MidYearDecommissioning::class,
            R2026_020_RenterExemption::class, // URL L. 421-140 = LEGIARTI000044602921 locked
            R2026_022_ContractualPeriodVsEffectiveUsage::class,
            R2026_024_CritAirGuard::class, // L. 421-134 cleanup 01/09/2026, no doctrinal impact
            // Inactive connected taxes (stable, reproduction).
            R2026_030_RegistrationWeightMalus::class, // inactive · 1600/1500 kg threshold to confirm
            R2026_032_HeavyVehiclesTax::class, // inactive · L. 421-145 stable since 2022
            // Inactive connected taxes (registration CO₂ malus, ADR-0022 editorial split Ordo 2025-1247 art. 4).
            R2026_029_RegistrationCo2Malus::class, // v 01/01-31/08/2026 · 108 g threshold, 80K€ cap
            R2026_029bis_RegistrationCo2Malus::class, // v 01/09-31/12/2026 · Ordo art. 4 cleanup
            // Inactive connected taxes (registration card taxes, ADR-0022 material split LF 2026 art. 60).
            R2026_031_RegistrationCardTaxes::class, // v 01/01-28/02/2026 · LF 2025 stabilised regime
            R2026_031bis_RegistrationCardTaxes::class, // v 01/03-31/12/2026 · L. 421-54-1 IDF surcharge +13 €
            // Inactive connected taxes (TAI 2026 full regime, ADR-0022 editorial split L. 421-132-5).
            R2026_033_FleetGreeningIncentiveTax::class, // v 01/01-28/02/2026 · textual transitional regime
            R2026_033bis_FleetGreeningIncentiveTax::class, // v 01/03-31/12/2026 · cleaned-up texts
        ];
    }
}
