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
 * Passe une déclaration `draft` en statut `deferred` (Phase 11 D3,
 * ADR-0015 § D4 + § 6.2). Indicateur visuel volontaire posé par
 * l'utilisateur : il met la déclaration de côté pour la trancher
 * plus tard (typiquement après consultation EC).
 *
 * Refus si la déclaration n'est pas `draft` (déjà generated, deferred,
 * obsolete...). Aucun bypass de la génération : `deferred` interdit
 * la génération comme `pending` (cf. `GenerateDeclarationAction`).
 *
 * ### Pattern toléré · mutation Eloquent directe sans WriteRepository
 *
 * Cette Action fait `$declaration->fill([...])->save()` au lieu de
 * passer par un `FiscalDeclarationWriteRepository::markAsDeferred()`.
 * Tolérance doctrinale validée user · plan-remediation Vague 1 Lot 4
 * § 14 (F-34-103) ·
 *
 *   « Mutation extrêmement légère (1 UPDATE sur la colonne `status`
 *     + métadonnées DB) ; le coût d'écriture d'un WriteRepository
 *     dédié serait disproportionné vs gain qualité. »
 *
 * Critères de l'exception ·
 *   - Action très simple · 1 mutation atomique, pas de logique métier
 *     complexe (les invariants `status === Draft` et `!is_obsolete`
 *     restent dans l'Action, pas dans une Rule métier dédiée).
 *   - Pas de validation cross-table.
 *   - Le pattern ne migre PAS dans un Controller (R1 strict respecté).
 *   - Tests Feature couvrent le comportement (statut, transitions
 *     refusées, observabilité Log).
 *
 * À reconsidérer si la logique de transition draft → deferred se
 * complexifie (ex. effets de bord, multi-mutations).
 *
 * Cf. ADR-0013 · architecture applicative R3 (Repositories).
 */
final readonly class MarkDeclarationAsDeferredAction
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

            if ($declaration->status !== FiscalDeclarationStatus::Draft) {
                throw new DomainException(sprintf(
                    'Seule une déclaration en statut « draft » peut être différée (statut courant : %s).',
                    $declaration->status->value,
                ));
            }

            if ($declaration->is_obsolete) {
                throw new DomainException('Une déclaration obsolète ne peut pas être différée ; régénérer une nouvelle déclaration.');
            }

            $declaration->fill(['status' => FiscalDeclarationStatus::Deferred])->save();

            Log::channel('declarations')->notice('FiscalDeclaration.marked_deferred', [
                'declaration_id' => $declaration->id,
                'company_id' => $declaration->company_id,
                'fiscal_year' => $declaration->fiscal_year,
                'actor_user_id' => Auth::id() ?? 0,
            ]);

            return $declaration->fresh();
        });
    }
}
