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
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

/**
 * Factory du `FleetFiscalAggregator` ad-hoc utilisé par
 * `DeclarationFiscalEngine` (Lot 4 D11 · F-19-004) pour appliquer les
 * opt-outs LCD runtime sans toucher au singleton aggregator standard.
 *
 * **Pourquoi un factory dédié** · la chaîne pipeline pour une
 * déclaration substitue R-YYYY-021 par sa version `WithOptOuts` via
 * un {@see OverlayedRuleRegistry}. Toutes les briques pipeline
 * (segmenter, executor, aggregator) doivent être **fraîches** ·
 * caches scopés à la déclaration, pas de partage avec le singleton.
 *
 * **Multi-année** · la paire (rule canonique, décorateur) est
 * sélectionnée selon `$year` (Bloc 4 Phase J). Ajouter une nouvelle
 * année = ajouter sa branche dans le `match` ci-dessous.
 */
final readonly class DeclarationAggregatorFactory
{
    public function __construct(
        private Container $container,
        private FiscalRuleRegistry $baseRegistry,
        private FiscalYearContext $yearContext,
        private VehicleFiscalCharacteristicsReadRepositoryInterface $vfcRepository,
        private FiscalRuleQueryService $fiscalRules,
        private CacheRepository $cache,
    ) {}

    /**
     * Construit un `FleetFiscalAggregator` ad-hoc dont la chaîne pipeline
     * est branchée sur un {@see OverlayedRuleRegistry} qui substitue
     * R-YYYY-021 par son décorateur runtime (WithOptOuts) pour cette
     * déclaration.
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
            $this->cache,
        );
    }
}
