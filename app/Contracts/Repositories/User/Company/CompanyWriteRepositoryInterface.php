<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\User\Company;

use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Company\UpdateCompanyData;
use App\Models\Company;

/**
 * Writes on the Company domain.
 */
interface CompanyWriteRepositoryInterface
{
    /**
     * Creates a company from the DTO + a short code generated upstream
     * by the Action (cf. CreateCompanyAction). The short code is no
     * longer part of the DTO (auto-generated, non-editable).
     */
    public function create(StoreCompanyData $data, string $shortCode): Company;

    /**
     * Updates the identity / address / contact fields of a company.
     * `short_code`, `is_active`, `is_oig`, `is_individual_business` are
     * not modified here (driven by other flows). Returns the refreshed
     * model.
     */
    public function update(int $companyId, UpdateCompanyData $data): Company;
}
