<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2024\Abatement\R2024_023_NoIsolatedAbatement;
use App\Fiscal\Year2024\Classification\R2024_004_FiscalTypeQualification;
use App\Fiscal\Year2024\Classification\R2024_005_Co2MethodSelection;
use App\Fiscal\Year2024\Classification\R2024_006_PaFallback;
use App\Fiscal\Year2024\Classification\R2024_013_PollutantCategoryAssignment;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_015_HandicapAccess;
use App\Fiscal\Year2024\Exemption\R2024_016_ElectricHydrogen;
use App\Fiscal\Year2024\Exemption\R2024_017_ConditionalHybridExemption;
use App\Fiscal\Year2024\Exemption\R2024_018_OigExemption;
use App\Fiscal\Year2024\Exemption\R2024_019_IndividualBusinessExemption;
use App\Fiscal\Year2024\Exemption\R2024_020_RenterExemption;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_026_SpecificActivityExemptions;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use App\Fiscal\Year2024\Pricing\R2024_014_PollutantsFlat;
use App\Fiscal\Year2024\Transversal\R2024_001_TaxpayerAndTriggeringEvent;
use App\Fiscal\Year2024\Transversal\R2024_002_DailyProrata;
use App\Fiscal\Year2024\Transversal\R2024_003_FinalRounding;
use App\Fiscal\Year2024\Transversal\R2024_007_VehicleCharacteristicsHistorization;
use App\Fiscal\Year2024\Transversal\R2024_009_MidYearDecommissioning;
use App\Fiscal\Year2024\Transversal\R2024_022_ContractualPeriodVsEffectiveUsage;
use App\Fiscal\Year2024\Transversal\R2024_024_CritAirGuard;
use App\Fiscal\Year2024\Transversal\R2024_025_WeightedAverageTariff;
use App\Fiscal\Year2024\Transversal\R2024_027_MileageReimbursementCoefficient;
use App\Fiscal\Year2024\Transversal\R2024_028_DeclarationModalities;
use App\Fiscal\Year2024\Transversal\R2024_029_RegistrationCo2Malus;
use App\Fiscal\Year2024\Transversal\R2024_030_RegistrationWeightMalus;
use App\Fiscal\Year2024\Transversal\R2024_031_RegistrationCardTaxes;
use App\Fiscal\Year2024\Transversal\R2024_032_HeavyVehiclesTax;
use App\Providers\FiscalServiceProvider;

/**
 * Catalog of 2024 fiscal rules (see `taxes-rules/2024.md`).
 *
 * Referenced by `config('floty.fiscal.year_boots')` and instantiated by
 * {@see FiscalServiceProvider} at boot. Edit {@see rules()} to change
 * the 2024 rule set; the provider does not need changes.
 */
final class Year2024Boot implements FiscalYearBoot
{
    public function year(): int
    {
        return 2024;
    }

    /**
     * @return list<class-string<FiscalRule>>
     */
    public function rules(): array
    {
        return [
            // Classification
            R2024_004_FiscalTypeQualification::class,
            R2024_005_Co2MethodSelection::class,
            R2024_013_PollutantCategoryAssignment::class,
            // Exemption
            R2024_008_ReductiveUnavailability::class,
            R2024_015_HandicapAccess::class,
            R2024_016_ElectricHydrogen::class,
            R2024_017_ConditionalHybridExemption::class,
            R2024_018_OigExemption::class,
            R2024_019_IndividualBusinessExemption::class,
            R2024_021_ShortTermRental::class,
            R2024_026_SpecificActivityExemptions::class,
            // Pricing
            R2024_010_WltpProgressive::class,
            R2024_011_NedcProgressive::class,
            R2024_012_PaProgressive::class,
            R2024_014_PollutantsFlat::class,
            // Transversal
            R2024_002_DailyProrata::class,
            R2024_003_FinalRounding::class,
            R2024_027_MileageReimbursementCoefficient::class,
        ];
    }

    /**
     * Documentary-only rules seeded into `fiscal_rules` to feed the
     * "Règles de calcul" page (User/FiscalRules/Index). They do not
     * participate in the calculation pipeline (cf. {@see InformativeRule}).
     * Cover architectural principles (R-2024-001 taxpayer, R-2024-007
     * VFC historisation, R-2024-009 mid-year decommissioning,
     * R-2024-020 renter, R-2024-022 contractual period vs usage), empty
     * cases (R-2024-023 abatements in 2024) and UI controls (R-2024-024
     * Crit'Air guard).
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [
            R2024_001_TaxpayerAndTriggeringEvent::class,
            R2024_006_PaFallback::class,
            R2024_007_VehicleCharacteristicsHistorization::class,
            R2024_009_MidYearDecommissioning::class,
            R2024_020_RenterExemption::class,
            R2024_022_ContractualPeriodVsEffectiveUsage::class,
            R2024_023_NoIsolatedAbatement::class,
            R2024_024_CritAirGuard::class,
            R2024_025_WeightedAverageTariff::class,
            R2024_028_DeclarationModalities::class,
            R2024_029_RegistrationCo2Malus::class,
            R2024_030_RegistrationWeightMalus::class,
            R2024_031_RegistrationCardTaxes::class,
            R2024_032_HeavyVehiclesTax::class,
        ];
    }
}
