<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalDeclaration;

use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures FiscalDeclaration (Phase 11 D1, ADR-0015 § 5.1 rev. 1.1).
 *
 * « Active » = non obsolète. Plusieurs déclarations peuvent coexister
 * pour un couple `(company, year)` grâce à la chaîne d'obsolescence ;
 * une seule à la fois est active (invariant garanti par les
 * Actions / observers, pas par contrainte SQL).
 */
interface FiscalDeclarationReadRepositoryInterface
{
    public function findById(int $id): ?FiscalDeclaration;

    /**
     * Déclaration active (non obsolète) pour le couple `(company, year)`.
     */
    public function findActiveForCompanyYear(int $companyId, int $year): ?FiscalDeclaration;

    /**
     * Historique chronologique (ancienne → récente) des déclarations
     * pour le couple, obsolètes incluses. Sert à la fiche entreprise et
     * à la page Show pour la trace d'audit.
     *
     * @return Collection<int, FiscalDeclaration>
     */
    public function findHistoryForCompanyYear(int $companyId, int $year): Collection;
}
