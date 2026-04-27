<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Company;

use App\Data\User\Company\StoreCompanyData;
use App\Models\Company;

/**
 * Écritures sur le domaine Company.
 */
interface CompanyWriteRepositoryInterface
{
    /**
     * Crée une entreprise à partir du DTO Spatie. Mapping camelCase →
     * snake_case explicite (Spatie Data n'expose `MapInputName` que
     * pour la désérialisation entrante, pas pour `->all()`).
     */
    public function create(StoreCompanyData $data): Company;
}
