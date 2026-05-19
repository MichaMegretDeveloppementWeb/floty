<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Data\User\Company\UpdateCompanyData;
use App\Models\Company;

/**
 * Updates an existing company. Identity (except `short_code`), address
 * and contact fields are editable; `short_code` stays immutable once
 * generated (see {@see UpdateCompanyData}).
 */
final class UpdateCompanyAction
{
    public function __construct(
        private readonly CompanyWriteRepositoryInterface $companyWriteRepo,
    ) {}

    public function execute(int $companyId, UpdateCompanyData $data): Company
    {
        return $this->companyWriteRepo->update($companyId, $data);
    }
}
