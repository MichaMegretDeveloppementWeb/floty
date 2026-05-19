<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Fiscal\Year2025\Classification\R2025_004_FiscalTypeQualification;
use App\Fiscal\Year2025\Classification\R2025_004bis_FiscalTypeQualification;
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
use App\Fiscal\Year2025\Transversal\R2025_001bis_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2025\Transversal\R2025_002_DailyProrata;
use App\Fiscal\Year2025\Transversal\R2025_003_FinalRounding;
use App\Fiscal\Year2025\Transversal\R2025_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2025\Transversal\R2025_009_MidYearDecommissioning;
use App\Fiscal\Year2025\Transversal\R2025_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2025\Transversal\R2025_024_CritAirGuard;
use App\Fiscal\Year2025\Transversal\R2025_025_WeightedAverageTariff;
use App\Fiscal\Year2025\Transversal\R2025_027_MileageReimbursementCoefficient;
use App\Fiscal\Year2025\Transversal\R2025_028_DeclarationModalities;
use App\Fiscal\Year2025\Transversal\R2025_028bis_DeclarationModalities;
use App\Fiscal\Year2025\Transversal\R2025_029_RegistrationCo2Malus;
use App\Fiscal\Year2025\Transversal\R2025_029bis_RegistrationCo2Malus;
use App\Fiscal\Year2025\Transversal\R2025_030_RegistrationWeightMalus;
use App\Fiscal\Year2025\Transversal\R2025_031_RegistrationCardTaxes;
use App\Fiscal\Year2025\Transversal\R2025_031bis_RegistrationCardTaxes;
use App\Fiscal\Year2025\Transversal\R2025_032_HeavyVehiclesTax;
use App\Fiscal\Year2025\Transversal\R2025_033_FleetGreeningIncentiveTax;
use App\Providers\FiscalServiceProvider;

/**
 * 2025 fiscal rules catalogue (see `taxes-rules/2025.md`).
 *
 * Referenced by `config('floty.fiscal.year_boots')` and instantiated by
 * {@see FiscalServiceProvider} at boot time.
 *
 * Major changes vs 2024:
 * - Daily prorata denominator = 365 (non-leap year, vs 366 in 2024).
 * - WLTP/NEDC/PA scales hardened by LF 2024 art. 97, 19° at 01/01/2025.
 * - Conditional hybrid exemption R-2024-017 removed at 01/01/2025.
 * - New: E85 abatement R-2025-023 (CIBS L. 421-125 reformed).
 * - ADR-0022 strict split: 3 rules have 2 versions in 2025 following
 *   LF 2025 art. 28 (effective 01/03/2025): R-2025-001/001-bis (taxpayer),
 *   R-2025-004/004-bis (M1/N1), R-2025-028/028-bis (declaration). Each
 *   legal period = its own PHP class with applicabilityStart/End.
 *
 * Strict isolation: no `App\Fiscal\Year2024\*` class is referenced nor
 * used in the 2025 pipeline (ADR-0022 and the 2025 conformity report).
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
        // 4 Classification + 7 Exemption + 4 Pricing + 1 Abatement + 3 Transversal = 19 classes.
        return [
            // Classification (R-2025-004 split into 2 versions, ADR-0022,
            // L. 421-2 modified 01/03/2025 by LF 2025 art. 28).
            R2025_004_FiscalTypeQualification::class, // 01/01-28/02
            R2025_004bis_FiscalTypeQualification::class, // 01/03-31/12
            R2025_005_Co2MethodSelection::class,
            R2025_013_PollutantCategoryAssignment::class,
            // Exemption (R-2025-017 hybrid removed vs 2024).
            R2025_008_ReductiveUnavailability::class,
            R2025_015_HandicapAccess::class,
            R2025_016_ElectricHydrogen::class,
            R2025_018_OigExemption::class,
            R2025_019_IndividualBusinessExemption::class,
            R2025_021_ShortTermRental::class,
            R2025_026_SpecificActivityExemptions::class,
            // Pricing CO₂ (major hardening 2025).
            R2025_010_WltpProgressive::class,
            R2025_011_NedcProgressive::class,
            R2025_012_PaProgressive::class,
            // Pricing pollutants (identical to 2024).
            R2025_014_PollutantsFlat::class,
            // Abatement (new in 2025: E85).
            R2025_023_E85Abatement::class,
            // Transversal.
            R2025_002_DailyProrata::class,
            R2025_003_FinalRounding::class,
            R2025_027_MileageReimbursementCoefficient::class,
        ];
    }

    /**
     * Documentation-only rules seeded into `fiscal_rules` to feed the
     * "Règles de calcul" page but which do NOT participate in the
     * calculation pipeline (see {@see InformativeRule}).
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [
            // R-2025-001 split by ADR-0022 (L. 421-95 + L. 421-98
            // modified on 01/03/2025).
            R2025_001_TaxpayerAndTriggeringEvent::class, // 01/01-28/02
            R2025_001bis_TaxpayerAndTriggeringEvent::class, // 01/03-31/12
            R2025_006_PaFallback::class,
            R2025_007_VehicleCharacteristicsHistorization::class,
            R2025_009_MidYearDecommissioning::class,
            R2025_020_RenterExemption::class,
            R2025_022_ContractualPeriodVsEffectiveUsage::class,
            R2025_024_CritAirGuard::class,
            R2025_025_WeightedAverageTariff::class,
            // R-2025-028 split by ADR-0022 (L. 421-159 + L. 421-164
            // modified on 01/03/2025).
            R2025_028_DeclarationModalities::class, // 01/01-28/02
            R2025_028bis_DeclarationModalities::class, // 01/03-31/12
            // R-2025-029 split by ADR-0022 (hardening 01/03/2025:
            // threshold 118→113 g, cap 60K→70K€, removal of 50% ceiling).
            R2025_029_RegistrationCo2Malus::class, // 01/01-28/02
            R2025_029bis_RegistrationCo2Malus::class, // 01/03-31/12
            R2025_030_RegistrationWeightMalus::class,
            // R-2025-031 split by ADR-0022 (evolution 01/05/2025:
            // optional regional Y1 exemption for EV/H₂).
            R2025_031_RegistrationCardTaxes::class, // 01/01-30/04
            R2025_031bis_RegistrationCardTaxes::class, // 01/05-31/12
            R2025_032_HeavyVehiclesTax::class,
            // New in 2025: no 2024 equivalent.
            R2025_033_FleetGreeningIncentiveTax::class,
        ];
    }
}
