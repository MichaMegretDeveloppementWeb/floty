<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\User\Assignment\AssignmentReadRepositoryInterface;
use App\Contracts\Repositories\User\Assignment\AssignmentWriteRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Repositories\User\Assignment\AssignmentReadRepository;
use App\Repositories\User\Assignment\AssignmentWriteRepository;
use App\Repositories\User\Company\CompanyReadRepository;
use App\Repositories\User\Company\CompanyWriteRepository;
use App\Repositories\User\FiscalRule\FiscalRuleReadRepository;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use App\Repositories\User\Vehicle\VehicleReadRepository;
use App\Repositories\User\Vehicle\VehicleWriteRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Enregistre le binding Contract → Implémentation pour chaque Repository
 * Floty. Le conteneur injecte ainsi l'implémentation Eloquent dès qu'un
 * service type-hint un {@see \App\Contracts\Repositories\...} dans son
 * constructeur.
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
        // Vehicle
        VehicleReadRepositoryInterface::class => VehicleReadRepository::class,
        VehicleWriteRepositoryInterface::class => VehicleWriteRepository::class,
        VehicleFiscalCharacteristicsReadRepositoryInterface::class => VehicleFiscalCharacteristicsReadRepository::class,

        // Company
        CompanyReadRepositoryInterface::class => CompanyReadRepository::class,
        CompanyWriteRepositoryInterface::class => CompanyWriteRepository::class,

        // Assignment
        AssignmentReadRepositoryInterface::class => AssignmentReadRepository::class,
        AssignmentWriteRepositoryInterface::class => AssignmentWriteRepository::class,

        // FiscalRule
        FiscalRuleReadRepositoryInterface::class => FiscalRuleReadRepository::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_keys($this->singletons);
    }
}
