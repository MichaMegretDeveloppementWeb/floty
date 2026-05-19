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
 * Registers the fiscal rule registry, per-year boot classes and shared
 * fiscal service singletons.
 *
 * Supported years are configured via `floty.fiscal.year_boots`; each year
 * ships a `Year{YYYY}Boot` implementing {@see FiscalYearBoot} and
 * declares its own rules. Adding a year only requires creating the boot
 * class and listing it in config (see
 * `project-management/taxes-rules/_adding-a-new-year.md`).
 *
 * {@see LcdQualifier} is bound globally to its 2024 implementation since
 * CIBS L.421-129 has been unchanged since 2022-01-01. Contextual bindings
 * pair each year's R-YYYY-008 with its matching R-YYYY-021 to prevent
 * cross-year contamination (ADR-0022).
 *
 * Rules living outside the pipeline (architectural, structural, UX) are
 * documented in ADR-0006 § 2 and are not registered here:
 *   - R-YYYY-001, R-YYYY-007, R-YYYY-009, R-YYYY-020, R-YYYY-024
 *   - R-2024-023 (placeholder, only R-2025-023 E85 is active)
 *
 * {@see VehicleFiscalCharacteristicsReadRepository} owns the historisation
 * logic of R-YYYY-007.
 */
final class FiscalServiceProvider extends ServiceProvider
{
    /**
     * Register the fiscal rule registry, the per-year boot classes and the
     * shared fiscal service singletons. Each singleton owns per-request
     * caches that must be shared across all consumers within an HTTP
     * request to avoid recomputing the same pipeline several times.
     *
     * @throws InvalidArgumentException If a configured boot class does not
     *                                  exist or does not implement
     *                                  {@see FiscalYearBoot}.
     */
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

        $this->app->singleton(RuleEffectiveSegmenter::class);
        $this->app->singleton(FleetFiscalAggregator::class);
        $this->app->singleton(RiskDetectionService::class);

        $this->app->bind(LcdQualifier::class, R2024_021_ShortTermRental::class);

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
