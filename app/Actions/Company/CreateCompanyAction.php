<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Data\User\Company\StoreCompanyData;
use App\Exceptions\Company\CompanyShortCodeCollisionException;
use App\Models\Company;

/**
 * Creates a company and auto-generates its short code from the legal name.
 * Verifies uniqueness against the UNIQUE constraint; raises a typed
 * exception converted into a `legal_name` field error by the controller.
 */
final class CreateCompanyAction
{
    public function __construct(
        private readonly CompanyReadRepositoryInterface $companyReadRepo,
        private readonly CompanyWriteRepositoryInterface $companyWriteRepo,
    ) {}

    public function execute(StoreCompanyData $data): Company
    {
        $shortCode = Company::generateShortCode($data->legalName);

        if ($this->companyReadRepo->existsByShortCode($shortCode)) {
            throw CompanyShortCodeCollisionException::forCode($shortCode);
        }

        return $this->companyWriteRepo->create($data, $shortCode);
    }
}
