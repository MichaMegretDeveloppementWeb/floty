<?php

declare(strict_types=1);

namespace App\Fiscal\Year2024;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Year2024\Classification\R2024_004_FiscalTypeQualification;
use App\Fiscal\Year2024\Classification\R2024_005_Co2MethodSelection;
use App\Fiscal\Year2024\Classification\R2024_013_PollutantCategoryAssignment;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_015_HandicapAccess;
use App\Fiscal\Year2024\Exemption\R2024_016_ElectricHydrogen;
use App\Fiscal\Year2024\Exemption\R2024_017_ConditionalHybridExemption;
use App\Fiscal\Year2024\Exemption\R2024_018_OigExemption;
use App\Fiscal\Year2024\Exemption\R2024_019_IndividualBusinessExemption;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use App\Fiscal\Year2024\Pricing\R2024_014_PollutantsFlat;
use App\Fiscal\Year2024\Transversal\R2024_002_DailyProrata;
use App\Fiscal\Year2024\Transversal\R2024_003_FinalRounding;
use App\Providers\FiscalServiceProvider;

/**
 * Catalogue des règles fiscales 2024 (cf. `taxes-rules/2024.md`).
 *
 * Référencée par `config('floty.fiscal.year_boots')` et instanciée par
 * {@see FiscalServiceProvider} au boot.
 *
 * Pour modifier le périmètre des règles 2024 (ajout, retrait, réordonnance),
 * éditer la liste {@see rules()} ci-dessous — sans toucher au provider.
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
            // Pricing
            R2024_010_WltpProgressive::class,
            R2024_011_NedcProgressive::class,
            R2024_012_PaProgressive::class,
            R2024_014_PollutantsFlat::class,
            // Transversal
            R2024_002_DailyProrata::class,
            R2024_003_FinalRounding::class,
        ];
    }
}
