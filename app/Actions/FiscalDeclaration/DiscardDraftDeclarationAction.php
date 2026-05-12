<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Enums\FiscalDeclaration\InvalidationReasonType;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Supprime un brouillon de déclaration fiscale et gère intelligemment
 * la chaîne `superseded_by_id` côté predecessor (Phase 13 D5.10.E).
 *
 * Pipeline atomique :
 *   1. Vérifie que la déclaration cible est un brouillon non finalisé
 *      (status Draft OU Deferred). Refuse toute suppression d'une
 *      Generated (active ou obsolète) · les déclarations émises sont
 *      immuables conformément à ADR-0008 et l'audit Doctrine.
 *   2. Recherche un éventuel predecessor (`X.superseded_by_id = draft.id`).
 *   3. Soft delete le brouillon.
 *   4. Si predecessor existe :
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
 */
final readonly class DiscardDraftDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $draftDeclarationId): void
    {
        DB::transaction(function () use ($draftDeclarationId): void {
            $draft = $this->reader->findById($draftDeclarationId);

            if ($draft === null) {
                throw new DomainException(sprintf('Déclaration %d introuvable.', $draftDeclarationId));
            }

            // Phase 13 D5.10.H · Draft ET Deferred sont supprimables ·
            // ce sont des brouillons non finalisés. Seules les Generated
            // sont protégées par l'immuabilité fiscale.
            if (! in_array($draft->status, [FiscalDeclarationStatus::Draft, FiscalDeclarationStatus::Deferred], true)) {
                throw new DomainException(
                    'Seul un brouillon (statut Draft ou Deferred) peut être supprimé. Les déclarations générées sont immuables.',
                );
            }

            $predecessor = $this->reader->findPredecessorOf($draft->id);

            $this->writer->softDelete($draft->id);

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

            Log::info('FiscalDeclaration draft discarded', [
                'draft_id' => $draft->id,
                'company_id' => $draft->company_id,
                'fiscal_year' => $draft->fiscal_year,
                'predecessor_id' => $predecessor?->id,
                'predecessor_reactivated' => $predecessorReactivated,
            ]);
        });
    }
}
