<?php

declare(strict_types=1);

namespace App\Actions\FiscalDeclaration;

use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationWriteRepositoryInterface;
use App\Enums\FiscalDeclaration\FiscalDeclarationStatus;
use App\Models\FiscalDeclaration;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Crée le record initial d'une déclaration fiscale en statut `draft`
 * pour un couple `(company, year)` (Phase 11 D4).
 *
 * **Refus** si une déclaration active existe déjà pour ce couple.
 * Une déclaration active est définie comme `is_obsolete = false`,
 * indépendamment de son statut `draft` / `deferred` / `generated`.
 * Si une obsolète existe, l'utilisateur doit passer par
 * {@see RegenerateDeclarationAction} (workflow régénération distinct).
 *
 * Action appelée par le bouton « Préparer la déclaration » de la
 * fiche entreprise (CompanyShow onglet Fiscalité).
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
            $existing = $this->reader->findActiveForCompanyYear($companyId, $year);
            if ($existing !== null) {
                throw new DomainException(sprintf(
                    'Une déclaration active existe déjà pour cette entreprise et l\'année %d (id=%d, statut=%s).',
                    $year,
                    $existing->id,
                    $existing->status->value,
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
