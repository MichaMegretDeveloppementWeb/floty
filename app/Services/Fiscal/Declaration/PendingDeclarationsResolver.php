<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\PendingDeclarationData;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Résout la liste des `(company, year)` qui méritent l'attention de
 * l'utilisateur sur l'onglet Vue d'ensemble d'une fiche entreprise
 * (Phase 11 D4, refondu Phase 12 D5.9.D).
 *
 * **Sémantique « pending » élargie (D5.9.D)** : la liste contient toute
 * année qui n'est pas en `GeneratedActive` (= finalisée et à jour).
 * Sont donc inclus :
 *   - Untouched (jamais préparée), sauf l'année en cours qui n'est pas
 *     encore due.
 *   - DraftPending / DraftReadyToGenerate (préparation en cours).
 *   - Deferred (mise de côté volontaire).
 *   - GeneratedObsoleteOrphan (déclaration périmée sans régénération).
 *   - RegenerationInProgress (Draft chaîné en cours).
 *
 * Chaque entry porte le `state` de cycle de vie résolu par
 * {@see DeclarationLifecycleResolver}, permettant au composant
 * frontend de proposer le bon CTA adaptatif (Préparer / Reprendre /
 * Régénérer / Reprendre la régénération).
 *
 * **Deadline** : 30 avril N+1 (CIBS officiel France). `isOverdue` est
 * dérivé via comparaison avec `now()`.
 *
 * Sortie triée plus ancienne année en premier (les retards les plus
 * critiques en haut de la card UI).
 */
final readonly class PendingDeclarationsResolver
{
    public function __construct(
        private DeclarationLifecycleResolver $lifecycleResolver,
        private FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    /**
     * @return list<PendingDeclarationData>
     */
    public function pendingForCompany(int $companyId): array
    {
        $contractYears = $this->yearsCoveredByContractsForCompany($companyId);
        if ($contractYears === []) {
            return [];
        }

        $now = CarbonImmutable::now();
        $currentYear = $now->year;

        $pending = [];
        foreach ($contractYears as $year) {
            $lifecycle = $this->lifecycleResolver->resolveForCompanyYear($companyId, $year);

            if ($lifecycle->state === DeclarationLifecycleState::GeneratedActive) {
                continue;
            }
            // Untouched sur l'année en cours · pas encore due tant que
            // l'exercice n'est pas clos. Les autres états (Draft,
            // Deferred, ObsoleteOrphan, Regen) méritent toujours
            // l'attention, même sur l'année courante.
            if (
                $lifecycle->state === DeclarationLifecycleState::Untouched
                && $year >= $currentYear
            ) {
                continue;
            }

            $deadline = sprintf('%04d-04-30', $year + 1);
            $isOverdue = $now->isAfter(CarbonImmutable::parse($deadline));

            [$obsoleteSinceDate, $obsoleteReasonsCount] = $this->resolveObsoleteContext(
                $companyId,
                $year,
                $lifecycle->state,
                $lifecycle->currentDeclaration?->id,
            );

            $pending[] = new PendingDeclarationData(
                fiscalYear: $year,
                deadline: $deadline,
                isOverdue: $isOverdue,
                state: $lifecycle->state,
                currentDeclarationId: $lifecycle->currentDeclaration?->id,
                pendingClustersCount: $lifecycle->pendingClustersCount,
                obsoleteSinceDate: $obsoleteSinceDate,
                obsoleteReasonsCount: $obsoleteReasonsCount,
            );
        }

        usort(
            $pending,
            static fn (PendingDeclarationData $a, PendingDeclarationData $b): int => $a->fiscalYear <=> $b->fiscalYear,
        );

        return $pending;
    }

    /**
     * Résout `(obsoleteSinceDate, obsoleteReasonsCount)` pour les
     * états S6 et S7 (Phase 13 D5.10.A). Pour S6, la déclaration
     * courante elle-même porte l'obsolescence. Pour S7, c'est son
     * prédécesseur (la version Generated obsolète remplacée par le
     * Draft chaîné).
     *
     * @return array{0: ?string, 1: int}
     */
    private function resolveObsoleteContext(
        int $companyId,
        int $year,
        DeclarationLifecycleState $state,
        ?int $currentDeclarationId,
    ): array {
        if ($state === DeclarationLifecycleState::GeneratedObsoleteOrphan) {
            $current = $this->declarations->findCurrentForCompanyYear($companyId, $year);
            if ($current === null) {
                return [null, 0];
            }

            return [
                $current->obsolete_at?->toDateString(),
                count($current->obsolete_reasons ?? []),
            ];
        }

        // Phase 13 D5.10.H · DeferredRegeneration partage la sémantique
        // de RegenerationInProgress · les motifs sont sur le predecessor
        // (la version Generated obsolète remplacée par ce Draft mis de
        // côté).
        if (
            in_array($state, [
                DeclarationLifecycleState::RegenerationInProgress,
                DeclarationLifecycleState::DeferredRegeneration,
            ], true)
            && $currentDeclarationId !== null
        ) {
            $predecessor = $this->declarations->findPredecessorOf($currentDeclarationId);
            if ($predecessor === null) {
                return [null, 0];
            }

            return [
                $predecessor->obsolete_at?->toDateString(),
                count($predecessor->obsolete_reasons ?? []),
            ];
        }

        return [null, 0];
    }

    /**
     * @return list<int>
     */
    private function yearsCoveredByContractsForCompany(int $companyId): array
    {
        $rows = DB::table('contracts')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->select(['start_date', 'end_date'])
            ->get();

        $years = [];
        foreach ($rows as $row) {
            $start = CarbonImmutable::parse((string) $row->start_date);
            $end = CarbonImmutable::parse((string) $row->end_date);

            for ($y = $start->year; $y <= $end->year; $y++) {
                $years[$y] = true;
            }
        }

        $list = array_keys($years);
        sort($list);

        return $list;
    }
}
