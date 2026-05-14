<?php

declare(strict_types=1);

namespace App\Fiscal\Year2025;

use App\Fiscal\Contracts\FiscalRule;
use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\InformativeRule;
use App\Fiscal\Year2025\Abatement\R2025_023_E85Abatement;
use App\Fiscal\Year2025\Classification\R2025_004_FiscalTypeQualification;
use App\Fiscal\Year2025\Classification\R2025_005_Co2MethodSelection;
use App\Fiscal\Year2025\Classification\R2025_013_PollutantCategoryAssignment;
use App\Fiscal\Year2025\Exemption\R2025_008_ReductiveUnavailability;
use App\Fiscal\Year2025\Exemption\R2025_015_HandicapAccess;
use App\Fiscal\Year2025\Exemption\R2025_016_ElectricHydrogen;
use App\Fiscal\Year2025\Exemption\R2025_018_OigExemption;
use App\Fiscal\Year2025\Exemption\R2025_019_IndividualBusinessExemption;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_026_SpecificActivityExemptions;
use App\Fiscal\Year2025\Pricing\R2025_010_WltpProgressive;
use App\Fiscal\Year2025\Pricing\R2025_011_NedcProgressive;
use App\Fiscal\Year2025\Pricing\R2025_012_PaProgressive;
use App\Fiscal\Year2025\Pricing\R2025_014_PollutantsFlat;
use App\Fiscal\Year2025\Transversal\R2025_002_DailyProrata;
use App\Fiscal\Year2025\Transversal\R2025_003_FinalRounding;
use App\Fiscal\Year2025\Transversal\R2025_027_MileageReimbursementCoefficient;
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
     * Phase I (à venir) · 14 classes documentaires-only seront ajoutées.
     *
     * @return list<class-string<InformativeRule>>
     */
    public function informativeRules(): array
    {
        return [];
    }
}
