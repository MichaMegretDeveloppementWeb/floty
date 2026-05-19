<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\FiscalDeclaration\FiscalDeclarationReadRepositoryInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Generates the next human-readable reference for a fiscal declaration.
 *
 * Format · `DECL-{shortCode}-{year}-{NNNN}` where `NNNN` is a 4-digit
 * counter per `(company_id, fiscal_year)` pair. The counter includes
 * soft-deleted declarations whose `reference` is non-null, so numbers
 * never regress after obsolescence (regeneration after `0001` produces
 * `0002` even if `0001` was soft-deleted).
 *
 * Atomic · `DB::transaction` + `lockForUpdate` on the rows of the pair
 * during the COUNT, as a defensive measure against intra-process
 * concurrency.
 */
final readonly class DeclarationReferenceGenerator
{
    public function __construct(
        private CompanyReadRepositoryInterface $companies,
        private FiscalDeclarationReadRepositoryInterface $declarations,
    ) {}

    public function generateFor(int $companyId, int $year): string
    {
        return DB::transaction(function () use ($companyId, $year): string {
            $company = $this->companies->findById($companyId);
            if ($company === null) {
                throw new RuntimeException(sprintf('Entreprise %d introuvable.', $companyId));
            }

            $existingCount = $this->declarations->countWithTrashedForReference($companyId, $year);

            return sprintf(
                'DECL-%s-%d-%04d',
                $company->short_code,
                $year,
                $existingCount + 1,
            );
        });
    }
}
