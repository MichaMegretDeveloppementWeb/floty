<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractReadRepositoryInterface;
use App\Contracts\Repositories\User\Contract\ContractWriteRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentReadRepositoryInterface;
use App\Contracts\Repositories\User\ContractDocument\ContractDocumentWriteRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverReadRepositoryInterface;
use App\Contracts\Repositories\User\Driver\DriverWriteRepositoryInterface;
use App\Contracts\Repositories\User\FiscalRule\FiscalRuleReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityReadRepositoryInterface;
use App\Contracts\Repositories\User\Unavailability\UnavailabilityWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleReadRepositoryInterface;
use App\Contracts\Repositories\User\Vehicle\VehicleWriteRepositoryInterface;
use App\Repositories\User\Company\CompanyReadRepository;
use App\Repositories\User\Company\CompanyWriteRepository;
use App\Repositories\User\Contract\ContractReadRepository;
use App\Repositories\User\Contract\ContractWriteRepository;
use App\Repositories\User\ContractDocument\ContractDocumentReadRepository;
use App\Repositories\User\ContractDocument\ContractDocumentWriteRepository;
use App\Repositories\User\Driver\DriverReadRepository;
use App\Repositories\User\Driver\DriverWriteRepository;
use App\Repositories\User\FiscalRule\FiscalRuleReadRepository;
use App\Repositories\User\Unavailability\UnavailabilityReadRepository;
use App\Repositories\User\Unavailability\UnavailabilityWriteRepository;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsReadRepository;
use App\Repositories\User\Vehicle\VehicleFiscalCharacteristicsWriteRepository;
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
        VehicleFiscalCharacteristicsWriteRepositoryInterface::class => VehicleFiscalCharacteristicsWriteRepository::class,

        // Company
        CompanyReadRepositoryInterface::class => CompanyReadRepository::class,
        CompanyWriteRepositoryInterface::class => CompanyWriteRepository::class,

        // Contract (ADR-0014) - entité pivot du domaine fiscal
        ContractReadRepositoryInterface::class => ContractReadRepository::class,
        ContractWriteRepositoryInterface::class => ContractWriteRepository::class,

        // ContractDocument (chantier 04.N) - PDF joints aux contrats
        ContractDocumentReadRepositoryInterface::class => ContractDocumentReadRepository::class,
        ContractDocumentWriteRepositoryInterface::class => ContractDocumentWriteRepository::class,

        // Driver (Phase 06 V1.2) - many-to-many avec Company via pivot driver_company
        DriverReadRepositoryInterface::class => DriverReadRepository::class,
        DriverWriteRepositoryInterface::class => DriverWriteRepository::class,

        // FiscalRule
        FiscalRuleReadRepositoryInterface::class => FiscalRuleReadRepository::class,

        // Unavailability
        UnavailabilityReadRepositoryInterface::class => UnavailabilityReadRepository::class,
        UnavailabilityWriteRepositoryInterface::class => UnavailabilityWriteRepository::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return array_keys($this->singletons);
    }
}
