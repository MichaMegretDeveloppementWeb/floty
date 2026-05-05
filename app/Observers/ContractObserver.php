<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Contract;
use App\Services\Fiscal\AvailableYearsResolver;

/**
 * Invalide le cache des bornes d'années sélectionnables (chantier η,
 * Phase 0.2) à chaque mutation d'un {@see Contract}.
 *
 * **Pourquoi un Observer plutôt qu'un Event Listener ou une invalidation
 * dans les Actions** :
 *   - **Couverture totale** : capte aussi les mutations passant par
 *     factories, seeders, console (`tinker`), tests, sans que ces
 *     chemins aient besoin de connaître le resolver.
 *   - **Single source of truth** : 1 seul fichier porte la responsabilité
 *     « toute mutation de Contract = invalidation du cache des années ».
 *   - **Découplage** : les Actions Contract restent agnostiques du
 *     resolver (séparation des préoccupations ADR-0013).
 *
 * **Branchement** : déclaré sur le modèle {@see Contract} via l'attribut
 * `#[ObservedBy([ContractObserver::class])]` (Laravel 11+, cohérent avec
 * `#[Fillable]` déjà utilisé sur ce modèle). Pas de bind dans
 * `AppServiceProvider::boot()`.
 *
 * **Hooks couverts** : created / updated / deleted / restored /
 * forceDeleted. Couvre l'intégralité du cycle de vie possible (incluant
 * la pose et le retrait du `deleted_at` qui modifient le périmètre des
 * contrats actifs vu par {@see AvailableYearsResolver}).
 */
final class ContractObserver
{
    public function __construct(
        private readonly AvailableYearsResolver $resolver,
    ) {}

    public function created(Contract $contract): void
    {
        $this->resolver->forgetCache();
    }

    public function updated(Contract $contract): void
    {
        $this->resolver->forgetCache();
    }

    public function deleted(Contract $contract): void
    {
        $this->resolver->forgetCache();
    }

    public function restored(Contract $contract): void
    {
        $this->resolver->forgetCache();
    }

    public function forceDeleted(Contract $contract): void
    {
        $this->resolver->forgetCache();
    }
}
