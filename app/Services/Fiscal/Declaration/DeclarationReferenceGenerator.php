<?php

declare(strict_types=1);

namespace App\Services\Fiscal\Declaration;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Models\FiscalDeclaration;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Calcule la prochaine référence lisible d'une déclaration fiscale
 * (Phase 11 D5.3).
 *
 * Format produit : `DECL-{shortCode}-{year}-{NNNN}`, où `NNNN` est un
 * compteur séquentiel à 4 chiffres par couple `(company_id, fiscal_year)`.
 *
 * **Stratégie de séquencement** : compte les déclarations existantes
 * **incluant les soft-deleted** dont la `reference` n'est pas null pour
 * le couple, puis +1. Les références ne reculent jamais après
 * obsolescence (la régénération produit `0002` même si la précédente
 * `0001` est devenue obsolete et soft-deleted).
 *
 * **Atomicité** : `DB::transaction` + `lockForUpdate` sur les rows du
 * couple pendant le COUNT, mesure défensive contre une éventuelle
 * concurrence intra-process.
 *
 * **Pas de consommateur production en D5.3** : ce service est branché à
 * {@see App\Actions\Fiscal\GenerateDeclarationAction} en D5.5 lors du
 * passage `Draft → Generated`.
 */
final readonly class DeclarationReferenceGenerator
{
    public function __construct(
        private CompanyReadRepositoryInterface $companies,
    ) {}

    public function generateFor(int $companyId, int $year): string
    {
        return DB::transaction(function () use ($companyId, $year): string {
            $company = $this->companies->findById($companyId);
            if ($company === null) {
                throw new RuntimeException(sprintf('Entreprise %d introuvable.', $companyId));
            }

            $existingCount = FiscalDeclaration::withTrashed()
                ->where('company_id', $companyId)
                ->where('fiscal_year', $year)
                ->whereNotNull('reference')
                ->lockForUpdate()
                ->count();

            return sprintf(
                'DECL-%s-%d-%04d',
                $company->short_code,
                $year,
                $existingCount + 1,
            );
        });
    }
}
