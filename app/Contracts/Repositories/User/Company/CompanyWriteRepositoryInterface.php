<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Company;

use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Company\UpdateCompanyData;
use App\Models\Company;

/**
 * Écritures sur le domaine Company.
 */
interface CompanyWriteRepositoryInterface
{
    /**
     * Crée une entreprise à partir du DTO Spatie + d'un code court généré
     * en amont par l'Action (cf. CreateCompanyAction). Le code court n'est
     * plus dans le DTO depuis le chantier A V1.2 (auto-généré, non éditable).
     */
    public function create(StoreCompanyData $data, string $shortCode): Company;

    /**
     * Met à jour les champs identité / adresse / contact d'une entreprise.
     * Le `short_code`, `is_active`, `is_oig`, `is_individual_business` ne
     * sont pas modifiés ici (pilotés par d'autres flux). Retourne le
     * Model rafraîchi.
     */
    public function update(int $companyId, UpdateCompanyData $data): Company;
}
