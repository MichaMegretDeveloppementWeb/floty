<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalReviewDecision;

use App\Models\FiscalReviewDecision;

/**
 * FiscalReviewDecision writes (ADR-0015 § 5.2).
 *
 * A decision is never `update` in the classic sense: a user can change
 * their mind on the same cluster (same fingerprint), but the database
 * only keeps one row per `(company, year, fingerprint)`. The `upsert`
 * pattern reflects this behaviour.
 */
interface FiscalReviewDecisionWriteRepositoryInterface
{
    /**
     * Creates or replaces the decision identified by
     * `(company_id, fiscal_year, cluster_fingerprint)`. Audit attributes
     * (`decided_by`, `decided_at`) are set on every call.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(array $attributes): FiscalReviewDecision;

    /**
     * Deletes all review decisions of a `(company, fiscal_year)` couple.
     * Called by {@see DiscardDraftDeclarationAction} to ensure a deleted
     * draft does not leave its decisions behind · otherwise they would
     * be auto-rehydrated by `DeclarationPreviewService` on the next
     * draft created for the same couple (the uniqueness key being
     * `(company, year, fingerprint)`, not the declaration id).
     *
     * Returns the number of decisions erased (audit / log).
     */
    public function deleteByCompanyYear(int $companyId, int $fiscalYear): int;
}
