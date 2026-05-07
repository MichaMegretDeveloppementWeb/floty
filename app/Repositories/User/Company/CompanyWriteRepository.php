<?php

declare(strict_types=1);

namespace App\Repositories\User\Company;

use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Data\User\Company\StoreCompanyData;
use App\Data\User\Company\UpdateCompanyData;
use App\Models\Company;

/**
 * Implémentation Eloquent des écritures Company.
 */
final class CompanyWriteRepository implements CompanyWriteRepositoryInterface
{
    public function create(StoreCompanyData $data, string $shortCode): Company
    {
        return Company::create([
            'legal_name' => $data->legalName,
            'short_code' => $shortCode,
            'color' => $data->color,
            'siren' => $data->siren,
            'siret' => $data->siret,
            'address_line_1' => $data->addressLine1,
            'address_line_2' => $data->addressLine2,
            'postal_code' => $data->postalCode,
            'city' => $data->city,
            'country' => $data->country,
            'contact_name' => $data->contactName,
            'contact_email' => $data->contactEmail,
            'contact_phone' => $data->contactPhone,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(int $companyId, UpdateCompanyData $data): Company
    {
        $company = Company::findOrFail($companyId);
        $company->update([
            'legal_name' => $data->legalName,
            'color' => $data->color,
            'siren' => $data->siren,
            'siret' => $data->siret,
            'address_line_1' => $data->addressLine1,
            'address_line_2' => $data->addressLine2,
            'postal_code' => $data->postalCode,
            'city' => $data->city,
            'country' => $data->country,
            'contact_name' => $data->contactName,
            'contact_email' => $data->contactEmail,
            'contact_phone' => $data->contactPhone,
        ]);

        return $company->fresh() ?? $company;
    }
}
