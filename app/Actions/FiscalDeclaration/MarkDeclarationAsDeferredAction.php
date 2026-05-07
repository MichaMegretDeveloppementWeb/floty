<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Passe une déclaration `draft` en statut `deferred` (Phase 11 D3,
 * ADR-0015 § D4 + § 6.2). Indicateur visuel volontaire posé par
 * l'utilisateur : il met la déclaration de côté pour la trancher
 * plus tard (typiquement après consultation EC).
 *
 * Refus si la déclaration n'est pas `draft` (déjà generated, deferred,
 * obsolete...). Aucun bypass de la génération : `deferred` interdit
 * la génération comme `pending` (cf. `GenerateDeclarationAction`).
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

            return $declaration->fresh();
        });
    }
}
