<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalReviewDecision\FiscalReviewDecisionWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Supprime un brouillon de déclaration fiscale et gère intelligemment
 * la chaîne `superseded_by_id` côté predecessor (Phase 13 D5.10.E,
 * durci Lot 5 D2 · locks pessimistes).
 *
 * Pipeline atomique :
 *   1. Lock pessimiste + soft delete sur la déclaration cible avec
 *      double-check du statut autorisé (Draft OU Deferred). Refuse
 *      toute suppression d'une Generated (active ou obsolète) · les
 *      déclarations émises sont immuables conformément à ADR-0008.
 *   2. Recherche un éventuel predecessor et le verrouille pessimiste
 *      pour la même transaction.
 *   3. Si predecessor existe :
 *      - Si **tous** ses `obsolete_reasons` sont du type
 *        `VoluntaryModification` (cf. {@see ModifyGeneratedDeclarationAction}) ·
 *        ré-active complètement le predecessor (S5 retrouvé). C'est le
 *        retour arrière propre d'une modification volontaire abandonnée.
 *      - Sinon (motifs réels présents · perimeter change) · délie
 *        seulement `superseded_by_id` pour que le predecessor reste
 *        obsolète (S6) et puisse être régénéré à nouveau plus tard.
 *
 * Note · soft delete via `Model::delete()` (le modèle FiscalDeclaration
 * utilise le trait `SoftDeletes`). Le record reste interrogeable via
 * `withTrashed()` pour l'audit forensic mais sort des requêtes par
 * défaut, ce qui libère le slot « active » du couple `(company, year)`
 * et permet à `CreateDraftDeclarationAction::findActiveForCompanyYear`
 * de retourner null à la prochaine tentative de préparation.
 *
 * Lot 5 D2 · les locks pessimistes (Draft + Predecessor) ferment la
 * fenêtre TOCTOU entre le `findById` (qui n'apparait plus) et les
 * mutations. Indispensable en production où plusieurs utilisateurs
 * peuvent agir en concurrence sur la même entreprise.
 */
final readonly class DiscardDraftDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
        private FiscalReviewDecisionWriteRepositoryInterface $decisionsWriter,
    ) {}

    public function execute(int $draftDeclarationId): FiscalDeclarationStatus
    {
        return DB::transaction(function () use ($draftDeclarationId): FiscalDeclarationStatus {
            // Lot 5 D2 · lock + double-check status atomique. Throws
            // DomainException si la déclaration n'existe plus ou si son
            // statut n'est plus supprimable (mutation concurrente).
            $draft = $this->writer->softDeleteWithLock($draftDeclarationId, [
                FiscalDeclarationStatus::Draft,
                FiscalDeclarationStatus::Deferred,
            ]);

            $originalStatus = $draft->status;

            // Lot 5 D2 · lock pessimiste sur le predecessor pour
            // prévenir une mutation concurrente entre la lecture du lien
            // et la décision reactivate vs unlink. Le predecessor est la
            // déclaration qui pointe vers ce draft via `superseded_by_id`
            // (rev. ADR-0015 § D8 chaîne d'obsolescence).
            $predecessor = null;
            $found = $this->reader->findPredecessorOf($draft->id);
            if ($found !== null) {
                $predecessor = $this->writer->lockPredecessor($found->id);
            }

            // D5.10.R · Efface les décisions de revue (clusters tranchés,
            // exclusions de contrats, justifications) du couple
            // `(company, fiscal_year)`. Sans ça, `DeclarationPreviewService`
            // les rechargerait automatiquement au prochain brouillon créé
            // (la clé d'unicité est `(company, year, fingerprint)`, pas
            // l'id de déclaration · cf. ADR-0015 § 6.5 « reprise auto à
            // la régénération »).
            //
            // OK même si une déclaration générée co-existe pour le même
            // couple · ses décisions sont déjà figées dans son
            // `generated_snapshot_payload`. Les rows
            // `fiscal_review_decisions` servent uniquement à reprendre
            // un brouillon en cours (cf. décision Lot 5 D2 sur F-19D2-003 ·
            // la purge globale est intentionnelle, pas un bug).
            $deletedDecisions = $this->decisionsWriter->deleteByCompanyYear(
                $draft->company_id,
                $draft->fiscal_year,
            );

            $predecessorReactivated = false;
            if ($predecessor !== null) {
                $reasons = is_array($predecessor->obsolete_reasons) ? $predecessor->obsolete_reasons : [];
                $isVoluntaryOnly = $reasons !== []
                    && array_reduce(
                        $reasons,
                        static fn (bool $carry, array $r): bool => $carry
                            && ($r['type'] ?? null) === InvalidationReasonType::VoluntaryModification->value,
                        true,
                    );

                if ($isVoluntaryOnly) {
                    $this->writer->reactivate($predecessor->id);
                    $predecessorReactivated = true;
                } else {
                    $this->writer->unlinkSupersededBy($predecessor->id);
                }
            }

            Log::channel('declarations')->notice('FiscalDeclaration.draft_discarded', [
                'draft_id' => $draft->id,
                'company_id' => $draft->company_id,
                'fiscal_year' => $draft->fiscal_year,
                'original_status' => $originalStatus->value,
                'predecessor_id' => $predecessor?->id,
                'predecessor_reactivated' => $predecessorReactivated,
                'review_decisions_deleted' => $deletedDecisions,
                'actor_user_id' => Auth::id() ?? 0,
            ]);

            return $originalStatus;
        });
    }
}
