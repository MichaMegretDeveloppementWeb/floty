<?php

declare(strict_types=1);

namespace App\Providers;

use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Year2024\Classification\R2024_005_Co2MethodSelection;
use App\Fiscal\Year2024\Exemption\R2024_015_HandicapAccess;
use App\Fiscal\Year2024\Exemption\R2024_016_ElectricHydrogen;
use App\Fiscal\Year2024\Exemption\R2024_021_LowDayCount;
use App\Fiscal\Year2024\Pricing\R2024_010_WltpProgressive;
use App\Fiscal\Year2024\Pricing\R2024_011_NedcProgressive;
use App\Fiscal\Year2024\Pricing\R2024_012_PaProgressive;
use App\Fiscal\Year2024\Pricing\R2024_014_PollutantsFlat;
use App\Fiscal\Year2024\Transversal\R2024_002_DailyProrata;
use App\Fiscal\Year2024\Transversal\R2024_003_FinalRounding;
use Illuminate\Support\ServiceProvider;

/**
 * Enregistre le {@see FiscalRuleRegistry} en singleton et y déclare les
 * classes règles applicables par année.
 *
 * Pour ajouter une année (2025, 2026…) : créer les classes sous
 * `app/Fiscal/Year{YYYY}/...` et appeler `$registry->register({YYYY}, [...])`
 * dans cette méthode.
 */
final class FiscalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FiscalRuleRegistry::class, function ($app): FiscalRuleRegistry {
            $registry = new FiscalRuleRegistry($app);
            $registry->register(2024, [
                R2024_005_Co2MethodSelection::class,
                R2024_015_HandicapAccess::class,
                R2024_016_ElectricHydrogen::class,
                R2024_021_LowDayCount::class,
                R2024_010_WltpProgressive::class,
                R2024_011_NedcProgressive::class,
                R2024_012_PaProgressive::class,
                R2024_014_PollutantsFlat::class,
                R2024_002_DailyProrata::class,
                R2024_003_FinalRounding::class,
            ]);

            return $registry;
        });
    }
}
