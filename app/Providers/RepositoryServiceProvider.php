<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Enregistre le binding Contract → Implémentation pour chaque Repository
 * Floty. Le conteneur injecte ainsi l'implémentation Eloquent dès qu'un
 * service type-hint un {@see \App\Contracts\Repositories\...} dans son
 * constructeur.
 *
 * Les bindings sont peuplés au fil des phases domaine (04-11). Ce
 * provider reste donc vide en phase 01 — la machinerie est prête à
 * recevoir les entrées.
 *
 * Les repositories Floty étant sans état (requêtes Eloquent stateless),
 * on les enregistre en **singletons** : une seule instance réutilisée
 * par requête HTTP, ce qui évite des instanciations répétées quand
 * plusieurs services l'injectent.
 */
final class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Contract → Implémentation. Ajouter une entrée ici dès qu'un nouveau
     * Repository est créé dans `app/Repositories/`.
     *
     * @var array<class-string, class-string>
     */
    public array $singletons = [
        // Phase 04 — Vehicle
        // \App\Contracts\Repositories\User\Vehicle\VehicleListReadRepositoryInterface::class
        //     => \App\Repositories\User\Vehicle\VehicleListReadRepository::class,
        //
        // Phase 05 — Company
        // \App\Contracts\Repositories\User\Company\CompanyListReadRepositoryInterface::class
        //     => \App\Repositories\User\Company\CompanyListReadRepository::class,
        //
        // … (compléter au fur et à mesure)
    ];

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_keys($this->singletons);
    }
}
