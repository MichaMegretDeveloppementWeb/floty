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
use Carbon\CarbonImmutable;
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
final class RiskDetectionService
{
    /**
     * Cache mémoire intra-instance des clusters détectés indexé par
     * `"{companyId}|{year}"` (Lot 3 D08).
     *
     * **Périmètre de sécurité** · purement per-request (Service Laravel
     * resolved par requête HTTP) · garbage-collecté en fin de requête,
     * aucun cache cross-request à invalider. Le contrat de mémoïsation
     * est sûr tant qu'aucun consommateur ne mute les contracts ou les
     * `FiscalRiskSettings` **entre deux appels intra-requête** sur le
     * même couple `(companyId, year)`.
     *
     * **Hot path bénéficiaire** · `GenerateDeclarationAction::execute`
     * et `DeclarationController` (preview+show) appellent successivement
     * `DeclarationPreviewService::preview()` puis
     * `DeclarationFiscalEngine::compute()`, qui chacun déclenche un
     * `detectClusters($companyId, $year)` · sans cache, le calcul
     * (chaînage LCD + fingerprint) est exécuté 2 fois.
     *
     * **Échappatoire** · {@see clearCache()} permet d'invalider
     * manuellement si jamais une Action future doit muter contracts puis
     * recalculer dans la même requête (cas non observé aujourd'hui).
     *
     * @var array<string, list<ReviewClusterData>>
     */
    private array $clustersCache = [];

    public function __construct(
        private readonly ContractReadRepositoryInterface $contracts,
        private readonly FiscalRiskSettingsReadRepositoryInterface $settingsRepo,
        private readonly LcdContractFilter $lcdFilter,
        private readonly FingerprintService $fingerprint,
    ) {}

    /**
     * @return list<ReviewClusterData>
     */
    public function detectClusters(int $companyId, int $year): array
    {
        $key = $companyId.'|'.$year;

        return $this->clustersCache[$key] ??= $this->computeClusters($companyId, $year);
    }

    /**
     * Vide le cache de mémoïsation per-request.
     *
     * À utiliser uniquement par les rares consommateurs qui mutent les
     * contracts ou les `FiscalRiskSettings` entre deux appels
     * `detectClusters()` dans la même requête HTTP. Pas nécessaire en
     * fonctionnement normal (le cache est garbage-collecté en fin de
     * requête).
     */
    public function clearCache(): void
    {
        $this->clustersCache = [];
    }

    /**
     * @return list<ReviewClusterData>
     */
    private function computeClusters(int $companyId, int $year): array
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
     * Algorithme de chaînage (ADR-0015 § 4, refondu D5.10.Q) · une
     * chaîne regroupe des LCD successifs séparés au plus de
     * `max_interval` jours pleins. Les contrats LLD intercalés sont
     * silencieusement ignorés · ils n'ont aucun rapport métier avec
     * une chaîne LCD (cohérence doctrine D5.10.N · la chaîne vise la
     * continuité temporelle d'usage en LCD, indépendamment d'autres
     * contrats LLD parallèles sur d'autres véhicules).
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
            if (! $this->lcdFilter->isLcd($contract)) {
                continue;
            }

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
     * **Lot 5 D1 · critère union des jours uniques couverts** · le seuil
     * de qualification se calcule sur l'union des jours **effectivement
     * couverts** par au moins un contrat de la chaîne (intervalles
     * fusionnés, bornés à l'année fiscale). Approche exacte qui couvre
     * uniformément deux pièges :
     *
     *   - **Contrats chevauchants** (ex. 3 contrats de 17 j superposés
     *     sur les mêmes 17 jours) · le cumul brut surcompterait
     *     (51 j faux), l'union restitue la réalité (17 j).
     *   - **Contrats avec trous** (ex. 15 j → trou 50 j → 8 j) · la
     *     plage début→fin surcompterait (73 j faux), l'union retient
     *     uniquement les jours d'usage réel (23 j).
     *
     * Le `coverageStartDate` / `coverageEndDate` continuent de désigner
     * la borne extérieure de la chaîne (premier début · dernier fin,
     * bornés à l'année) · ils servent uniquement à l'affichage UI du
     * header de cluster, pas au seuil.
     *
     * **Historique** · D5.10.N avait migré du cumul brut vers la plage
     * couverte ; cette doctrine a été remplacée par l'union des jours
     * (Lot 5 D1) après identification du surcomptage symétrique de
     * la plage en cas de trous internes.
     *
     * @param  list<Contract>  $chain
     */
    private function qualifyChain(array $chain, int $year, FiscalRiskSettings $settings): ?ReviewClusterData
    {
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        $intervals = $this->boundedIntervals($chain, $yearStart, $yearEnd);
        if ($intervals === []) {
            return null;
        }

        $coveragePeriodDays = $this->unionDays($intervals);

        // Bornes externes de la chaîne (pour l'affichage UI uniquement,
        // pas pour le seuil) · premier début ↔ dernier fin, bornés à l'année.
        $coverageStart = $intervals[0][0];
        $coverageEnd = $intervals[0][1];
        foreach ($intervals as [$start, $end]) {
            if ($start->lt($coverageStart)) {
                $coverageStart = $start;
            }
            if ($end->gt($coverageEnd)) {
                $coverageEnd = $end;
            }
        }

        $count = count($chain);
        $distinctVehiclesCount = count(array_unique(array_map(
            static fn (Contract $c): int => $c->vehicle_id,
            $chain,
        )));

        $code = match (true) {
            $coveragePeriodDays > $settings->threshold_high || $count >= $settings->count_high => RiskCode::ChainFort,
            $coveragePeriodDays > $settings->threshold_low => RiskCode::Chain,
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
            coveragePeriodDays: $coveragePeriodDays,
            coverageStartDate: $coverageStart->toDateString(),
            coverageEndDate: $coverageEnd->toDateString(),
            distinctVehiclesCount: $distinctVehiclesCount,
            decision: null,
            justification: null,
        );
    }

    /**
     * Renvoie la liste des intervalles `[start, end]` (CarbonImmutable)
     * de chaque contrat de la chaîne, bornés à l'année fiscale et
     * écartés s'ils tombent entièrement hors année.
     *
     * @param  list<Contract>  $chain
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function boundedIntervals(array $chain, CarbonImmutable $yearStart, CarbonImmutable $yearEnd): array
    {
        $intervals = [];
        foreach ($chain as $contract) {
            $start = $contract->start_date->lt($yearStart)
                ? $yearStart
                : CarbonImmutable::parse($contract->start_date);
            $end = $contract->end_date->gt($yearEnd)
                ? $yearEnd
                : CarbonImmutable::parse($contract->end_date);
            if ($end->lt($start)) {
                continue;
            }
            $intervals[] = [$start, $end];
        }

        return $intervals;
    }

    /**
     * Union des jours uniques couverts par au moins un intervalle.
     * Algorithme classique · tri par date de début, fusion des
     * intervalles chevauchants ou contigus (un intervalle qui démarre
     * le lendemain du précédent est fusionné · 1-10 ∪ 11-20 = 1-20),
     * puis somme inclusive des durées des intervalles fusionnés.
     *
     * @param  list<array{0: CarbonImmutable, 1: CarbonImmutable}>  $intervals
     */
    private function unionDays(array $intervals): int
    {
        usort(
            $intervals,
            static fn (array $a, array $b): int => $a[0]->getTimestamp() <=> $b[0]->getTimestamp(),
        );

        /** @var list<array{0: CarbonImmutable, 1: CarbonImmutable}> $merged */
        $merged = [$intervals[0]];
        $count = count($intervals);
        for ($i = 1; $i < $count; $i++) {
            [$start, $end] = $intervals[$i];
            $lastIndex = count($merged) - 1;
            [$lastStart, $lastEnd] = $merged[$lastIndex];

            if ($start->lessThanOrEqualTo($lastEnd->addDay())) {
                $merged[$lastIndex] = [
                    $lastStart,
                    $end->greaterThan($lastEnd) ? $end : $lastEnd,
                ];
            } else {
                $merged[] = [$start, $end];
            }
        }

        $total = 0;
        foreach ($merged as [$start, $end]) {
            $total += (int) $start->diffInDays($end) + 1;
        }

        return $total;
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
