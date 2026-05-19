<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepositoryInterface;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Models\FiscalReviewDecision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Creates or replaces the user decision for a risk cluster
 * (ADR-0015 § 6.2). Functional identity:
 * `(company_id, fiscal_year, cluster_fingerprint)`.
 *
 * Doctrine: final arbitration belongs to the user; neither the UI
 * (`ClusterDecisionModal`) nor the backend block a "Conserver"
 * decision on a high-severity cluster without justification. The
 * justification stays recommended (label "recommandée pour risque
 * élevé" on the modal), the backend only enforces the length cap
 * (max 2000 chars) to protect the TEXT column.
 */
final readonly class StoreReviewDecisionAction
{
    public function __construct(
        private FiscalReviewDecisionWriteRepositoryInterface $writer,
        private ContractReadRepositoryInterface $contracts,
    ) {}

    public function execute(StoreReviewDecisionData $data, int $userId): FiscalReviewDecision
    {
        $this->guardJustificationLength($data);

        $excludedIds = $data->excludedContractIds !== null && $data->excludedContractIds !== []
            ? array_values(array_unique(array_map(static fn ($v): int => (int) $v, $data->excludedContractIds)))
            : null;

        if ($excludedIds !== null) {
            $this->guardExcludedContractsBelongToScope($data->companyId, $data->fiscalYear, $excludedIds);
        }

        $decision = DB::transaction(fn (): FiscalReviewDecision => $this->writer->upsert([
            'company_id' => $data->companyId,
            'fiscal_year' => $data->fiscalYear,
            'risk_code' => $data->riskCode,
            'cluster_fingerprint' => $data->clusterFingerprint,
            'decision' => $data->decision,
            'justification' => $data->justification,
            'excluded_contract_ids' => $excludedIds,
            'decided_by' => $userId,
            'decided_at' => Carbon::now(),
        ]));

        Log::channel('declarations')->notice('FiscalDeclaration.review_decision_stored', [
            'decision_id' => $decision->id,
            'company_id' => $data->companyId,
            'fiscal_year' => $data->fiscalYear,
            'risk_code' => $data->riskCode->value,
            'cluster_fingerprint_short' => substr($data->clusterFingerprint, 0, 12),
            'decision' => $data->decision->value,
            'has_justification' => $data->justification !== null && trim($data->justification) !== '',
            'actor_user_id' => $userId,
        ]);

        return $decision;
    }

    private const int JUSTIFICATION_MAX_LENGTH = 2000;

    /**
     * Caps the justification length to protect the TEXT column and
     * limit the storage surface. 2000 characters is enough for a
     * detailed fiscal justification (~300 words).
     */
    private function guardJustificationLength(StoreReviewDecisionData $data): void
    {
        if ($data->justification !== null && mb_strlen($data->justification) > self::JUSTIFICATION_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'La justification ne peut pas dépasser %d caractères (longueur reçue : %d).',
                self::JUSTIFICATION_MAX_LENGTH,
                mb_strlen($data->justification),
            ));
        }
    }

    /**
     * Verifies every `excludedContractIds` entry belongs to the
     * `(company_id, fiscal_year)` couple of the decision. Closes a
     * latent IDOR in a multi-tenant V2: without this guard, a payload
     * could forge `contract_id` from another company and pollute the
     * audit trail even though `DeclarationFiscalEngine` filters
     * unknown IDs via `in_array` (no computational effect, but still
     * persisted in `fiscal_review_decisions.excluded_contract_ids`).
     *
     * @param  list<int>  $excludedIds
     */
    private function guardExcludedContractsBelongToScope(int $companyId, int $fiscalYear, array $excludedIds): void
    {
        $allowedIds = $this->contracts
            ->findForCompanyAndYear($companyId, $fiscalYear)
            ->pluck('id')
            ->all();

        $unauthorized = array_values(array_diff($excludedIds, $allowedIds));
        if ($unauthorized === []) {
            return;
        }

        throw new InvalidArgumentException(sprintf(
            'Les contrats exclus suivants n\'appartiennent pas à l\'entreprise %d sur l\'exercice %d : %s.',
            $companyId,
            $fiscalYear,
            implode(', ', $unauthorized),
        ));
    }
}
