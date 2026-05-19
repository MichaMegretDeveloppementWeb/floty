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
 * Regenerates an obsolete declaration: creates a new `draft` row for
 * the `(company, year)` couple and chains the obsolete one via
 * `superseded_by_id` (ADR-0015 § 6.5).
 *
 * The `fiscal_review_decisions` are not duplicated: keyed by
 * `(company, year)`, they are automatically picked up by the new row
 * through fingerprint matching at the next preview:
 *   - unchanged fingerprint clusters: decision auto-reapplied
 *   - new or mutated clusters: back to `pending`
 *
 * The historical PDF is preserved on disk indefinitely, reachable
 * through `superseded_by_id` (immutability doctrine, ADR-0008).
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
