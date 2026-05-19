<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Data\User\FiscalDeclaration\PendingDeclarationData;
use App\Enums\FiscalDeclaration\DeclarationLifecycleState;
use Carbon\CarbonImmutable;

/**
 * Resolves the `(company, year)` pairs needing user attention on the
 * Company overview tab.
 *
 * "Pending" is anything not in `GeneratedActive` (finalised and
 * up-to-date). Includes ·
 *   - Untouched (never prepared), except the current year (not yet due).
 *   - DraftPending / DraftReadyToGenerate (in progress).
 *   - Deferred (intentionally set aside).
 *   - GeneratedObsoleteOrphan (obsolete declaration without regen).
 *   - RegenerationInProgress (chained Draft in progress).
 *
 * Each entry carries the lifecycle state resolved by
 * {@see DeclarationLifecycleResolver}, so the frontend can surface the
 * matching adaptive CTA (Préparer / Reprendre / Régénérer / …).
 *
 * Deadline · April 30 of N+1 (French CIBS). `isOverdue` is derived
 * against `now()`. Output sorted oldest year first.
 */
final readonly class PendingDeclarationsResolver
{
    public function __construct(
        private DeclarationLifecycleResolver $lifecycleResolver,
        private FiscalDeclarationReadRepositoryInterface $declarations,
        private ContractReadRepositoryInterface $contracts,
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
            // Untouched on the current year is not yet due (the fiscal
            // exercise is still open). Other states (Draft, Deferred,
            // ObsoleteOrphan, Regen) still need attention even on the
            // current year.
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
     * Resolves `(obsoleteSinceDate, obsoleteReasonsCount)` for S6 and
     * S7. S6 reads the current declaration itself; S7 reads its
     * predecessor (the obsolete Generated version superseded by the
     * chained Draft).
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
                // Defensive · `count($x ?? [])` is unsafe when `$x` is a
                // string (Eloquent cast that returned a scalar from
                // corrupted JSON); `count(string)` throws TypeError in
                // PHP 8. Mirrors `InvalidationReasonData::listFromRaw`.
                is_array($current->obsolete_reasons) ? count($current->obsolete_reasons) : 0,
            ];
        }

        // DeferredRegeneration shares the same semantics as
        // RegenerationInProgress · reasons live on the predecessor (the
        // obsolete Generated version superseded by this deferred Draft).
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
                is_array($predecessor->obsolete_reasons) ? count($predecessor->obsolete_reasons) : 0,
            ];
        }

        return [null, 0];
    }

    /**
     * @return list<int>
     */
    private function yearsCoveredByContractsForCompany(int $companyId): array
    {
        return $this->contracts->findActiveYearsForCompany($companyId);
    }
}
