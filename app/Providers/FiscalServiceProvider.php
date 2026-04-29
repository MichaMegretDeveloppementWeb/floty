<?php

declare(strict_types=1);

namespace App\Providers;

use App\Fiscal\Registry\FiscalRuleRegistry;
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
use App\Fiscal\Year2024\Exemption\R2024_022_ActivityBasedExemption;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use App\Fiscal\Year2024\Pricing\R2024_014_PollutantsFlat;
use App\Fiscal\Year2024\Transversal\R2024_002_DailyProrata;
use App\Fiscal\Year2024\Transversal\R2024_003_FinalRounding;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Enregistre le {@see FiscalRuleRegistry} en singleton et y déclare les
 * classes règles applicables par année.
 *
 * **Pour ajouter une nouvelle année** : créer les classes sous
 * `app/Fiscal/Year{YYYY}/...`, ajouter une méthode privée
 * `registerYear{YYYY}()`, et l'appeler depuis {@see register()}.
 * Procédure complète documentée dans
 * `project-management/taxes-rules/_adding-a-new-year.md`.
 *
 * Note : certaines règles du catalogue 2024 ne sont **pas** enregistrées
 * ici car elles vivent hors pipeline (cf. ADR-0006 § 2) :
 * - R-2024-001 (redevable / fait générateur) : architecturale
 * - R-2024-007 (historisation des caractéristiques) : structurelle
 *   (gérée par {@see VehicleFiscalCharacteristicsReadRepository})
 * - R-2024-009 (mise hors-service) : UX produit (formulaire véhicule)
 * - R-2024-020 (loueur) : architecture Floty par construction
 * - R-2024-023 (abattements 2024) : placeholder vide, pas applicable
 *   en 2024
 * - R-2024-024 (garde-fou Crit'Air) : validation UI côté formulaire
 *   véhicule (`useCritAirCheck`)
 *
 * **Refonte 04.F (ADR-0014)** :
 * - R-2024-008 (indispos fiscalement réductrices) devient une vraie
 *   `ExemptionRule` souveraine (avant : filtrage SQL caché). Refonte
 *   à 4 cas réducteurs prévue en chantier 04.I (ADR-0016).
 * - R-2024-021 renommée `ShortTermRental` ; sémantique LCD per-contract.
 */
final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalRuleRegistry::class, function ($app): FiscalRuleRegistry {
            $registry = new FiscalRuleRegistry($app);

            $this->registerYear2024($registry);
            // Ajouter une année future ici :
            // $this->registerYear2025($registry);

            return $registry;
        });
    }

    /**
     * Catalogue des règles fiscales 2024 (cf.
     * `project-management/taxes-rules/2024.md`).
     */
    private function registerYear2024(FiscalRuleRegistry $registry): void
    {
        $registry->register(2024, [
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
            R2024_022_ActivityBasedExemption::class,
            // Pricing
            R2024_010_WltpProgressive::class,
            R2024_011_NedcProgressive::class,
            R2024_012_PaProgressive::class,
            R2024_014_PollutantsFlat::class,
            // Transversal
            R2024_002_DailyProrata::class,
            R2024_003_FinalRounding::class,
        ]);
    }
}
