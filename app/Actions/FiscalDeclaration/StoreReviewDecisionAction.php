<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepositoryInterface;
use App\Data\User\FiscalReviewDecision\StoreReviewDecisionData;
use App\Enums\FiscalReviewDecision\ReviewDecisionType;
use App\Enums\FiscalReviewDecision\RiskLevel;
use App\Models\FiscalReviewDecision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Crée ou remplace la décision humaine pour un cluster de risque
 * (Phase 11 D3, ADR-0015 § 6.2). Identité fonctionnelle :
 * `(company_id, fiscal_year, cluster_fingerprint)`.
 *
 * **Règle métier** : la justification est obligatoire ssi
 * `decision = Conserved` ET le code de risque est de niveau élevé
 * (cf. `RiskLevel::requiresJustificationOnConserved()`). Refus brutal
 * (`InvalidArgumentException`) sinon, pour ne pas laisser passer une
 * décision élevée non documentée.
 */
final readonly class StoreReviewDecisionAction
{
    public function __construct(
        private FiscalReviewDecisionWriteRepositoryInterface $writer,
    ) {}

    public function execute(StoreReviewDecisionData $data, int $userId): FiscalReviewDecision
    {
        $this->guardJustificationIfRequired($data);

        $excludedIds = $data->excludedContractIds !== null && $data->excludedContractIds !== []
            ? array_values(array_unique(array_map(static fn ($v): int => (int) $v, $data->excludedContractIds)))
            : null;

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

    /**
     * Limite la longueur d'une justification pour éviter le débordement
     * du champ TEXT BDD et limiter la surface de stockage. 2000
     * caractères suffit pour une justification fiscale détaillée
     * (équivalent ~300 mots, audit B17 pré-livraison).
     */
    private const int JUSTIFICATION_MAX_LENGTH = 2000;

    private function guardJustificationIfRequired(StoreReviewDecisionData $data): void
    {
        // Validation longueur : applicable même pour les Requalified
        // ou clusters niveau moyen (audit B17). On veille à ne jamais
        // accepter une justification de plusieurs MB (DOS du champ
        // TEXT MySQL ou stockage disproportionné).
        if ($data->justification !== null && mb_strlen($data->justification) > self::JUSTIFICATION_MAX_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'La justification ne peut pas dépasser %d caractères (longueur reçue : %d).',
                self::JUSTIFICATION_MAX_LENGTH,
                mb_strlen($data->justification),
            ));
        }

        if ($data->decision !== ReviewDecisionType::Conserved) {
            return;
        }

        if ($data->riskCode->level() !== RiskLevel::Eleve) {
            return;
        }

        if ($data->justification !== null && trim($data->justification) !== '') {
            return;
        }

        throw new InvalidArgumentException(
            'Une justification est obligatoire pour conserver l\'exonération sur un cluster de niveau élevé (ADR-0015 § 6.2).',
        );
    }
}
