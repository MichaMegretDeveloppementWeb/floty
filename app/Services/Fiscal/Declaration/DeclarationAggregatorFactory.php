<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Fiscal\Pipeline\FiscalPipeline;
use App\Fiscal\Pipeline\FiscalSegmentedExecutor;
use App\Fiscal\Pipeline\RuleEffectiveSegmenter;
use App\Fiscal\Registry\FiscalRuleRegistry;
use App\Fiscal\Registry\OverlayedRuleRegistry;
use App\Fiscal\Year2024\Exemption\R2024_021_ShortTermRental;
use App\Fiscal\Year2024\Exemption\R2024_021_WithOptOuts;
use App\Fiscal\Year2025\Exemption\R2025_021_ShortTermRental;
use App\Fiscal\Year2025\Exemption\R2025_021_WithOptOuts;
use App\Fiscal\Year2026\Exemption\R2026_021_ShortTermRental;
use App\Fiscal\Year2026\Exemption\R2026_021_WithOptOuts;
use App\Services\Fiscal\FleetFiscalAggregator;
use App\Services\FiscalRule\FiscalRuleQueryService;
use App\Services\Shared\Fiscal\FiscalYearContext;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Factory for the ad-hoc `FleetFiscalAggregator` consumed by
 * `DeclarationFiscalEngine` to apply runtime LCD opt-outs without
 * touching the standard singleton aggregator.
 *
 * The pipeline chain for a declaration substitutes R-YYYY-021 with its
 * `WithOptOuts` flavour through an {@see OverlayedRuleRegistry}. Every
 * pipeline brick (segmenter, executor, aggregator) must be fresh ·
 * caches scoped to the declaration, no sharing with the singleton.
 *
 * Multi-year · the `(canonical rule, decorator)` pair is picked from
 * `$year`. Adding a new year means adding its branch in the `match`.
 */
final readonly class DeclarationAggregatorFactory
{
    public function __construct(
        private Container $container,
        private FiscalRuleRegistry $baseRegistry,
        private FiscalYearContext $yearContext,
        private VehicleFiscalCharacteristicsReadRepositoryInterface $vfcRepository,
        private FiscalRuleQueryService $fiscalRules,
    ) {}

    /**
     * Builds an ad-hoc `FleetFiscalAggregator` whose pipeline chain
     * runs through an {@see OverlayedRuleRegistry} substituting
     * R-YYYY-021 with its runtime decorator (`WithOptOuts`) for this
     * declaration.
     *
     * @param  list<int>  $optOutContractIds
     */
    public function buildFor(int $year, array $optOutContractIds): FleetFiscalAggregator
    {
        $decorator = match ($year) {
            2024 => new R2024_021_WithOptOuts(
                $this->container->make(R2024_021_ShortTermRental::class),
                $optOutContractIds,
            ),
            2025 => new R2025_021_WithOptOuts(
                $this->container->make(R2025_021_ShortTermRental::class),
                $optOutContractIds,
            ),
            2026 => new R2026_021_WithOptOuts(
                $this->container->make(R2026_021_ShortTermRental::class),
                $optOutContractIds,
            ),
            default => throw new RuntimeException(sprintf(
                'Année fiscale non supportée pour le décorateur LCD · %d. Ajouter la paire (R-YYYY-021_ShortTermRental, R-YYYY-021_WithOptOuts) ici lors du portage de la nouvelle année.',
                $year,
            )),
        };

        $overlayedRegistry = new OverlayedRuleRegistry(
            $this->container,
            $this->baseRegistry,
            $decorator,
            $year,
        );
        $segmenter = new RuleEffectiveSegmenter($overlayedRegistry);
        $pipeline = new FiscalPipeline(
            $overlayedRegistry,
            $this->yearContext,
            $this->vfcRepository,
        );
        $executor = new FiscalSegmentedExecutor(
            $this->vfcRepository,
            $segmenter,
            $pipeline,
        );

        return new FleetFiscalAggregator(
            $executor,
            $this->yearContext,
            $this->fiscalRules,
            $this->vfcRepository,
        );
    }
}
