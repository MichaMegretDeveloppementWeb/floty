<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\FiscalDeclaration;

use App\Data\User\FiscalDeclaration\DeclarationIndexQueryData;
use App\Models\Company;
use App\Models\FiscalDeclaration;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /**
     * Liste paginée server-side de l'Index Déclarations (cf. ADR-0020).
     *
     * @return LengthAwarePaginator<int, FiscalDeclaration>
     */
    public function paginateForIndex(DeclarationIndexQueryData $query): LengthAwarePaginator;

    /**
     * `true` ssi au moins une déclaration existe en base. Utilisé par
     * l'Index pour distinguer « table intrinsèquement vide » du
     * « filtre actif retournant 0 ».
     */
    public function existsAny(): bool;

    /**
     * Bornes des années couvertes par les déclarations. Utilisé pour
     * piloter l'options list du sélecteur Année du filtre Index.
     *
     * Retourne `null` s'il n'y a aucune déclaration.
     *
     * @return array{min: int, max: int}|null
     */
    public function findYearBounds(): ?array;

    /**
     * Liste des entreprises ayant au moins une déclaration en base.
     * Sert à alimenter le sélecteur Entreprise du filtre Index.
     *
     * @return Collection<int, Company>
     */
    public function findCompanyOptions(): Collection;
}
