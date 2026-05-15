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
     * Déclaration **courante** pour le couple `(company, year)` : la
     * version **la plus avancée** de la chaîne, même obsolète (Phase
     * 11 D5.8 audit). Diffère de `findActiveForCompanyYear` qui filtre
     * `is_obsolete = false` et masque ainsi les déclarations
     * obsolètes orphelines.
     *
     * Définition : la déclaration courante est la « head » de la
     * chaîne `superseded_by_id`, c'est-à-dire le dernier maillon
     * (`superseded_by_id IS NULL`), la version qui n'a encore été
     * remplacée par personne. Si une régénération est en cours (un
     * Draft chaîné), c'est ce Draft qui est courant. Sinon c'est la
     * dernière Generated (active ou obsolète orpheline) ou un Draft
     * initial.
     *
     * Retourne null si aucune déclaration n'existe pour ce couple.
     */
    public function findCurrentForCompanyYear(int $companyId, int $year): ?FiscalDeclaration;

    /**
     * Déclaration prédécesseur immédiate dans la chaîne `superseded_by_id`
     * (Phase 11 D5.8). Si `$declaration` est un Draft chaîné en cours de
     * régénération, retourne la version obsolète qu'il remplace.
     * Utile pour `<ReviewContextBanner>` et `<DeclarationStateCard>`.
     */
    public function findPredecessorOf(int $declarationId): ?FiscalDeclaration;

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

    /**
     * Lot 4 D02 (F-34-002) · liste des déclarations en statut
     * `Generated` matchant l'un des couples `(company_id, fiscal_year)`
     * du produit cartésien `companyIds × years`. Eager-load
     * `company:short_code,legal_name` pour les toasts d'invalidation.
     *
     * Utilisé par {@see App\Services\Fiscal\Declaration\DeclarationInvalidationDetector}
     * lors de la propagation des mutations Contract / VFC / Vehicle /
     * Unavailability vers les déclarations émises (Phase 13 D5.10.O ·
     * seules les Generated sont marquées obsolètes, pas les
     * Draft/Deferred).
     *
     * @param  list<int>  $companyIds
     * @param  list<int>  $years
     * @return Collection<int, FiscalDeclaration>
     */
    public function findGeneratedForCompanyYears(array $companyIds, array $years): Collection;

    /**
     * Lot 4 D02 (F-34-004) · compte des déclarations existantes
     * (incluant les soft-deleted) du couple `(company_id, fiscal_year)`
     * dont la `reference` est non-null · sert au séquencement des
     * références lisibles `DECL-{shortCode}-{year}-{NNNN}` (cf.
     * {@see App\Services\Fiscal\Declaration\DeclarationReferenceGenerator}).
     *
     * **Invariant atomicité** · `lockForUpdate()` est appliqué sur les
     * rows du couple pendant le COUNT. L'appelant **doit** envelopper
     * dans une `DB::transaction(...)` sinon le verrou pessimiste est
     * inopérant.
     */
    public function countWithTrashedForReference(int $companyId, int $year): int;
}
