<?php

declare(strict_types=1);

namespace App\Providers;

use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Contracts\LcdQualifier;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Year2024\Exemption\R2024_008_ReductiveUnavailability;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_008_ReductiveUnavailability;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use App\Fiscal\Year2026\Exemption\R2026_008_ReductiveUnavailability;
use App\Fiscal\Year2026\Exemption\R2026_021_ShortTermRental;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\Fiscal\RiskDetection\RiskDetectionService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Enregistre le {@see FiscalRuleRegistry} en singleton et y déclare les
 * classes règles applicables par année.
 *
 * **Architecture (chantier ζ · extensibilité multi-année)** · la liste
 * des années supportées vit dans `config('floty.fiscal.year_boots')`.
 * Chaque année a sa classe `Year{YYYY}Boot` qui implémente
 * {@see FiscalYearBoot} et déclare ses propres règles. Le provider se
 * contente de boucler dessus · il ne **connaît plus** d'année particulière.
 *
 * **Pour ajouter une nouvelle année** · créer `app/Fiscal/Year{YYYY}/Year{YYYY}Boot.php`
 * + ajouter la classe dans la config. Procédure complète dans
 * `project-management/taxes-rules/_adding-a-new-year.md`.
 *
 * **Bindings LcdQualifier** (Bloc 4 Phase J · multi-année) :
 * - Binding par défaut · `LcdQualifier` → `R2024_021_ShortTermRental`.
 *   Utilisé par `LcdContractFilter` (risk detection cluster-by-fingerprint
 *   indépendante de l'année · les deux classes 2024 et 2025 ont une
 *   logique identique CIBS L. 421-129 inchangée depuis 01/01/2022).
 * - Binding contextuel `R-2024-008` → `R-2024-021` · alignement strict
 *   pipeline 2024.
 * - Binding contextuel `R-2025-008` → `R-2025-021` · alignement strict
 *   pipeline 2025 (sinon Laravel injecterait le binding global 2024 dans
 *   la règle 2025, viol ADR-0022).
 *
 * Note · certaines règles du catalogue 2024 ne sont **pas** enregistrées
 * via les `FiscalYearBoot` car elles vivent hors pipeline (cf. ADR-0006 § 2) :
 * - R-YYYY-001 (redevable / fait générateur) · architecturale
 * - R-YYYY-007 (historisation des caractéristiques) · structurelle
 *   (gérée par {@see VehicleFiscalCharacteristicsReadRepository})
 * - R-YYYY-009 (mise hors-service) · UX produit (formulaire véhicule)
 * - R-YYYY-020 (loueur) · architecture Floty par construction
 * - R-2024-023 (abattements 2024) · placeholder vide, pas applicable
 *   en 2024 (R-2025-023 E85 monte en pipeline 2025)
 * - R-YYYY-024 (garde-fou Crit'Air) · validation UI côté formulaire
 *   véhicule (`useCritAirCheck`)
 */
final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalRuleRegistry::class, function (Application $app): FiscalRuleRegistry {
            $registry = new FiscalRuleRegistry($app);

            $bootClasses = (array) config('floty.fiscal.year_boots', []);

            foreach ($bootClasses as $bootClass) {
                if (! is_string($bootClass) || ! class_exists($bootClass)) {
                    throw new InvalidArgumentException(
                        "FiscalYearBoot invalide dans config('floty.fiscal.year_boots') : ".var_export($bootClass, true)
                    );
                }

                $boot = $app->make($bootClass);

                if (! $boot instanceof FiscalYearBoot) {
                    throw new InvalidArgumentException(
                        $bootClass.' doit implémenter '.FiscalYearBoot::class
                    );
                }

                $registry->register($boot->year(), $boot->rules());
            }

            return $registry;
        });

        // RuleEffectiveSegmenter porte un cache mémoire process des
        // segments par année (chantier κ.3). Il doit être singleton
        // pour amortir le calcul sur plusieurs accès dans la même
        // requête HTTP.
        $this->app->singleton(RuleEffectiveSegmenter::class);

        // FleetFiscalAggregator porte 3 caches d'instance scopés
        // requête (`$rulesCache`, `$fullYearResultCache`,
        // `$fullYearBreakdownCache`) indexés par `(vehicleId, year)`.
        // Sans singleton, chaque service consommateur (VehicleListing,
        // ContractQueryService, DashboardStatsService, PlanningHeatmap,
        // CompanyDetail, etc.) recevait sa propre instance avec caches
        // vides · le pipeline pour le même couple (véhicule, année)
        // était recalculé 2-5× par requête HTTP. Le singleton partage
        // les caches sur toute la requête · gain mesuré ~30-50% sur
        // Dashboard et Contracts Index (audit perf 2026-05-15 · C-1).
        //
        // Note · `DeclarationAggregatorFactory` instancie son aggregator
        // ad-hoc via `new FleetFiscalAggregator(...)` (pas via container)
        // donc il garde son propre cache scopé à la déclaration ·
        // pas de cross-contamination avec le singleton.
        $this->app->singleton(FleetFiscalAggregator::class);

        // RiskDetectionService porte `$clustersCache` indexé par
        // `(companyId, year)`. Mêmes raisons · le `DeclarationFiscalEngine`
        // et le `PendingDeclarationsResolver` consomment tous les deux
        // `detectClusters()` dans la même requête · sans singleton, le
        // chaînage LCD + fingerprint était calculé 2× pour la même paire
        // (audit perf 2026-05-15 · C-1).
        $this->app->singleton(RiskDetectionService::class);

        // Binding par défaut · résolu par `LcdContractFilter` (risk
        // detection) qui n'a pas de contexte année spécifique au moment
        // de la résolution. Les deux classes 2024 et 2025 ont une logique
        // identique (CIBS L. 421-129 inchangé depuis 2022). Le
        // `DeclarationFiscalEngine` (D5.2) construit un
        // `OverlayedRuleRegistry` qui substitue localement cette
        // implémentation par un decorator porteur des décisions
        // « Requalified » du workflow de revue.
        $this->app->bind(LcdQualifier::class, R2024_021_ShortTermRental::class);

        // Bindings contextuels · chaque règle réductrice R-YYYY-008 doit
        // recevoir le LcdQualifier de SA propre année pour éviter toute
        // contamination cross-année dans le pipeline (ADR-0022).
        $this->app->when(R2024_008_ReductiveUnavailability::class)
            ->needs(LcdQualifier::class)
            ->give(R2024_021_ShortTermRental::class);

        $this->app->when(R2025_008_ReductiveUnavailability::class)
            ->needs(LcdQualifier::class)
            ->give(R2025_021_ShortTermRental::class);

        $this->app->when(R2026_008_ReductiveUnavailability::class)
            ->needs(LcdQualifier::class)
            ->give(R2026_021_ShortTermRental::class);
    }
}
