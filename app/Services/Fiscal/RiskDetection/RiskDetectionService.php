<?php

declare(strict_types=1);

namespace App\Services\Fiscal\RiskDetection;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRiskSettings\FiscalRiskSettingsReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\ClusterContractData;
use App\Data\User\FiscalDeclaration\ReviewClusterData;
use App\Enums\FiscalReviewDecision\RiskCode;
use App\Models\Contract;
use App\Models\FiscalRiskSettings;
use Illuminate\Support\Collection;

/**
 * Détecte les clusters de risque fiscal pour un couple
 * `(entreprise, année)` (Phase 11 D2, ADR-0015 § 4).
 *
 * Pure logique de calcul : aucune persistance, aucune mutation, aucune
 * IO autre que la lecture des contrats et des seuils. Sortie =
 * `list<ReviewClusterData>` consommable par les Actions D3 et la page
 * de revue D4.
 *
 * **Périmètre** (ADR § 3.1) : entreprise utilisatrice × année civile,
 * tous véhicules confondus. Les chaînes ne traversent pas les
 * entreprises.
 *
 * **Source de vérité LCD** : la règle fiscale `R-2024-021`
 * (`LcdContractFilter`). Le `contract_type` BDD est ignoré, conformément
 * à la doctrine des règles fiscales souveraines (mémoire
 * `feedback_fiscal_rules_authority`).
 *
 * **Déterminisme** : tri SQL `start_date ASC, id ASC` côté repo +
 * fingerprint trié par `id` ASC = même entrée → même sortie, garantie
 * fonctionnelle de la régénération à fingerprint identique (§ 6.5).
 */
final readonly class RiskDetectionService
{
    public function __construct(
        private ContractReadRepositoryInterface $contracts,
        private FiscalRiskSettingsReadRepositoryInterface $settingsRepo,
        private LcdContractFilter $lcdFilter,
        private FingerprintService $fingerprint,
    ) {}

    /**
     * @return list<ReviewClusterData>
     */
    public function detectClusters(int $companyId, int $year): array
    {
        $allContracts = $this->contracts->findForCompanyAndYear($companyId, $year);
        if ($allContracts->isEmpty()) {
            return [];
        }

        $settings = $this->settingsRepo->get();

        $chains = $this->buildChains($allContracts, $settings);

        $clusters = [];
        foreach ($chains as $chain) {
            $cluster = $this->qualifyChain($chain, $year, $settings);
            if ($cluster !== null) {
                $clusters[] = $cluster;
            }
        }

        return $clusters;
    }

    /**
     * Algorithme de chaînage (ADR-0015 § 4) : une chaîne regroupe des
     * LCD successifs séparés au plus de `max_interval` jours pleins.
     * Un LLD intercalé rompt la chaîne ssi `lld_breaks_chain = true`.
     *
     * @param  Collection<int, Contract>  $contracts
     * @return list<list<Contract>>
     */
    private function buildChains(Collection $contracts, FiscalRiskSettings $settings): array
    {
        /** @var list<list<Contract>> $chains */
        $chains = [];
        /** @var list<Contract> $current */
        $current = [];

        foreach ($contracts as $contract) {
            if ($this->lcdFilter->isLcd($contract)) {
                if ($current === []) {
                    $current = [$contract];

                    continue;
                }

                $previous = $current[count($current) - 1];
                $interval = $this->intervalDays($previous, $contract);

                if ($interval <= $settings->max_interval) {
                    $current[] = $contract;
                } else {
                    $this->flushChain($chains, $current);
                    $current = [$contract];
                }
            } elseif ($settings->lld_breaks_chain) {
                $this->flushChain($chains, $current);
                $current = [];
            }
        }

        $this->flushChain($chains, $current);

        return $chains;
    }

    /**
     * @param  list<list<Contract>>  $chains
     * @param  list<Contract>  $current
     */
    private function flushChain(array &$chains, array $current): void
    {
        if (count($current) >= 2) {
            $chains[] = $current;
        }
    }

    private function intervalDays(Contract $previous, Contract $next): int
    {
        return (int) $previous->end_date->copy()->diffInDays($next->start_date) - 1;
    }

    /**
     * Qualifie une chaîne en R-LCD-CHAIN, R-LCD-CHAIN-FORT ou rien.
     *
     * @param  list<Contract>  $chain
     */
    private function qualifyChain(array $chain, int $year, FiscalRiskSettings $settings): ?ReviewClusterData
    {
        $cumul = 0;
        foreach ($chain as $contract) {
            $cumul += count($contract->expandToDaysInYear($year));
        }

        $count = count($chain);

        $code = match (true) {
            $cumul > $settings->threshold_high || $count >= $settings->count_high => RiskCode::ChainFort,
            $cumul > $settings->threshold_low => RiskCode::Chain,
            default => null,
        };

        if ($code === null) {
            return null;
        }

        return new ReviewClusterData(
            code: $code,
            level: $code->level(),
            fingerprint: $this->fingerprint->compute($chain),
            contracts: $this->buildContractDtos($chain, $year),
            contractsCount: $count,
            cumulativeDaysInYear: $cumul,
            decision: null,
            justification: null,
        );
    }

    /**
     * @param  list<Contract>  $chain
     * @return list<ClusterContractData>
     */
    private function buildContractDtos(array $chain, int $year): array
    {
        $dtos = [];
        $previous = null;
        foreach ($chain as $contract) {
            $dtos[] = ClusterContractData::fromContract($contract, $year, $previous);
            $previous = $contract;
        }

        return $dtos;
    }
}
