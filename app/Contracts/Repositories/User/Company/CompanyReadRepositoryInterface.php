<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Company;

use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * Lectures sur le domaine Company.
 *
 * Toutes les requêtes Eloquent ciblant {@see Company} vivent ici. Les
 * services consomment ces méthodes pour orchestrer la logique métier
 * sans toucher à `Company::query()`.
 */
interface CompanyReadRepositoryInterface
{
    public function findById(int $id): ?Company;

    /**
     * Liste de toutes les entreprises pour la page « Entreprises
     * utilisatrices », triées par raison sociale.
     *
     * @return Collection<int, Company>
     */
    public function findAllOrderedByName(): Collection;

    /**
     * Liste des entreprises actives pour les `<SelectInput>`, colonnes
     * minimales, triées par raison sociale.
     *
     * @return Collection<int, Company>
     */
    public function findAllForOptions(): Collection;

    /**
     * Liste des entreprises actives pour la heatmap planning, colonnes
     * minimales, triées par code court.
     *
     * @return Collection<int, Company>
     */
    public function findAllForHeatmap(): Collection;

    /**
     * Compte les entreprises actives.
     */
    public function countActive(): int;

    /**
     * Précharge en bulk un ensemble d'entreprises par ids, indexées par
     * id. Inclut les colonnes nécessaires à l'affichage (raison sociale,
     * code court, couleur). Renvoie une collection vide si `$ids` l'est.
     *
     * @param  list<int>  $ids
     * @return Collection<int, Company>
     */
    public function findByIdsIndexed(array $ids): Collection;
}
