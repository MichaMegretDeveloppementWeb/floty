<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Creates the initial `draft` declaration record for a
 * `(company, year)` couple.
 *
 * Refuses if an active declaration already exists for that couple
 * (active = `is_obsolete = false`, regardless of `draft` / `deferred`
 * / `generated`). If an obsolete declaration exists, the user must
 * go through {@see RegenerateDeclarationAction} instead.
 *
 * Invoked from the "Préparer la déclaration" button on the
 * Company > Fiscalité tab.
 */
final readonly class CreateDraftDeclarationAction
{
    public function __construct(
        private FiscalDeclarationReadRepositoryInterface $reader,
        private FiscalDeclarationWriteRepositoryInterface $writer,
    ) {}

    public function execute(int $companyId, int $year): FiscalDeclaration
    {
        return DB::transaction(function () use ($companyId, $year): FiscalDeclaration {
            // Defence-in-depth guard: a declaration can only be
            // prepared after the fiscal year is closed (declaration N
            // is due on 30/04/N+1; scopes must be frozen).
            // `PendingDeclarationsResolver` already filters UI-side;
            // this catches direct POSTs and scripts.
            $currentYear = CarbonImmutable::now()->year;
            if ($year >= $currentYear) {
                throw new DomainException(sprintf(
                    'Une déclaration ne peut pas être préparée tant que l\'année fiscale n\'est pas terminée (%d).',
                    $year,
                ));
            }

            $existing = $this->reader->findActiveForCompanyYear($companyId, $year);
            if ($existing !== null) {
                // Technical details logged for debug, not exposed to
                // the user.
                Log::channel('declarations')->notice('FiscalDeclaration.draft_create_refused', [
                    'company_id' => $companyId,
                    'fiscal_year' => $year,
                    'existing_declaration_id' => $existing->id,
                    'existing_status' => $existing->status->value,
                    'existing_reference' => $existing->reference,
                    'reason' => 'active_declaration_already_exists',
                    'actor_user_id' => Auth::id() ?? 0,
                ]);

                throw new DomainException(sprintf(
                    'Une déclaration %d existe déjà pour cette entreprise. Ouvrez-la pour la consulter ou la régénérer.',
                    $year,
                ));
            }

            return $this->writer->persist([
                'company_id' => $companyId,
                'fiscal_year' => $year,
                'status' => FiscalDeclarationStatus::Draft,
                'is_obsolete' => false,
            ]);
        });
    }
}
