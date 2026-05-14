<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Régénère une déclaration obsolète : crée un nouveau record `draft`
 * pour le couple `(company, year)` et chaîne l'ancien via
 * `superseded_by_id` (Phase 11 D3, ADR-0015 § 6.5).
 *
 * **Aucune duplication des `fiscal_review_decisions`** : elles sont
 * rattachées au couple `(company, year)`, donc le nouveau record les
 * voit automatiquement via fingerprint matching au prochain
 * `DeclarationPreviewService::preview()`. Comportement § 6.5 :
 *   - clusters au fingerprint inchangé : décision auto-reprise
 *   - clusters nouveaux/modifiés : repassent en `pending`
 *
 * **Aucune suppression du PDF historique** : le fichier reste sur
 * disque indéfiniment, accessible via `superseded_by_id` (D8 +
 * doctrine immuabilité fiscale, ADR-0008).
 */
final readonly class RegenerateDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $obsoleteDeclarationId): FiscalDeclaration
    {
        return DB::transaction(function () use ($obsoleteDeclarationId): FiscalDeclaration {
            $obsolete = $this->reader->findById($obsoleteDeclarationId);
            if ($obsolete === null) {
                throw new DomainException(sprintf('Déclaration %d introuvable.', $obsoleteDeclarationId));
            }

            if (! $obsolete->is_obsolete) {
                throw new DomainException('Régénération réservée aux déclarations obsolètes ; utiliser la génération initiale sinon.');
            }

            $newDeclaration = $this->writer->persist([
                'company_id' => $obsolete->company_id,
                'fiscal_year' => $obsolete->fiscal_year,
                'status' => FiscalDeclarationStatus::Draft,
                'is_obsolete' => false,
            ]);

            $this->writer->linkSupersededBy($obsolete->id, $newDeclaration->id);

            Log::channel('declarations')->notice('FiscalDeclaration.regenerated', [
                'old_declaration_id' => $obsolete->id,
                'new_declaration_id' => $newDeclaration->id,
                'company_id' => $obsolete->company_id,
                'fiscal_year' => $obsolete->fiscal_year,
                'previous_reference' => $obsolete->reference,
                'actor_user_id' => Auth::id() ?? 0,
            ]);

            return $newDeclaration;
        });
    }
}
