<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Annule la mise en attente d'une déclaration · transition
 * `deferred → draft`. Réciproque exacte de
 * {@see MarkDeclarationAsDeferredAction}.
 *
 * **Contrat sémantique** · l'utilisateur reprend l'édition d'un
 * brouillon qu'il avait mis de côté · ses décisions de revue
 * (`fiscal_review_decisions`), son `superseded_by_id` éventuel, son
 * `obsolete_*` (cas DeferredRegeneration) sont **intégralement
 * préservés**. Seul le statut change.
 *
 * **Refus** si la déclaration n'est pas `deferred` (déjà draft, ou
 * generated, ou obsolete). Pas de bypass de la doctrine de génération.
 *
 * ### Pattern toléré · mutation Eloquent directe sans WriteRepository
 *
 * Même justification que {@see MarkDeclarationAsDeferredAction} ·
 * mutation extrêmement légère (1 UPDATE sur la colonne `status`),
 * pas de logique métier complexe, validation des invariants gardée
 * dans l'Action. Pattern documenté plan-remediation Vague 1 Lot 4
 * § 14 (F-34-103). Cf. ADR-0013 R3 (Repositories).
 */
final readonly class RevertDeferredToDraftAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
    ) {}

    public function execute(int $declarationId): FiscalDeclaration
    {
        return DB::transaction(function () use ($declarationId): FiscalDeclaration {
            $declaration = $this->reader->findById($declarationId);
            if ($declaration === null) {
                throw new DomainException(sprintf('Déclaration %d introuvable.', $declarationId));
            }

            if ($declaration->status !== FiscalDeclarationStatus::Deferred) {
                throw new DomainException(sprintf(
                    'Seule une déclaration en statut « deferred » peut voir sa mise en attente annulée (statut courant · %s).',
                    $declaration->status->value,
                ));
            }

            // Lot 5 D13 · clear `defer_reason` au revert · état transitoire
            // cohérent avec le statut · si l'utilisateur re-reporte plus
            // tard, il saisira une nouvelle raison fraîche. Pas
            // d'historique persistant.
            $declaration->fill([
                'status' => FiscalDeclarationStatus::Draft,
                'defer_reason' => null,
            ])->save();

            Log::channel('declarations')->notice('FiscalDeclaration.reverted_to_draft', [
                'declaration_id' => $declaration->id,
                'company_id' => $declaration->company_id,
                'fiscal_year' => $declaration->fiscal_year,
                'actor_user_id' => Auth::id() ?? 0,
            ]);

            return $declaration->fresh();
        });
    }
}
