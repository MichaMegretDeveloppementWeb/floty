<?php

declare(strict_types=1);

namespace App\Providers;

use App\Fiscal\Contracts\FiscalYearBoot;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Enregistre le {@see FiscalRuleRegistry} en singleton et y déclare les
 * classes règles applicables par année.
 *
 * **Architecture (chantier ζ — extensibilité multi-année)** : la liste
 * des années supportées vit dans `config('floty.fiscal.year_boots')`.
 * Chaque année a sa classe `Year{YYYY}Boot` qui implémente
 * {@see FiscalYearBoot} et déclare ses propres règles. Le provider se
 * contente de boucler dessus — il ne **connaît plus** d'année particulière.
 *
 * **Pour ajouter une nouvelle année** : créer `app/Fiscal/Year{YYYY}/Year{YYYY}Boot.php`
 * + ajouter la classe dans la config. Procédure complète dans
 * `project-management/taxes-rules/_adding-a-new-year.md`.
 *
 * Note : certaines règles du catalogue 2024 ne sont **pas** enregistrées
 * via les `FiscalYearBoot` car elles vivent hors pipeline (cf. ADR-0006 § 2) :
 * - R-2024-001 (redevable / fait générateur) : architecturale
 * - R-2024-007 (historisation des caractéristiques) : structurelle
 *   (gérée par {@see VehicleFiscalCharacteristicsReadRepository})
 * - R-2024-009 (mise hors-service) : UX produit (formulaire véhicule)
 * - R-2024-020 (loueur) : architecture Floty par construction
 * - R-2024-023 (abattements 2024) : placeholder vide, pas applicable
 *   en 2024
 * - R-2024-024 (garde-fou Crit'Air) : validation UI côté formulaire
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
    }
}
