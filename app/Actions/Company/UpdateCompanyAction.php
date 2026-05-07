<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Data\User\Company\UpdateCompanyData;
use App\Models\Company;

/**
 * Mise à jour d'une entreprise existante. Les champs identité (sauf
 * `short_code`), adresse et contact sont modifiables. Le `short_code`
 * reste immuable une fois généré (cf. note dans
 * {@see UpdateCompanyData}).
 *
 * Slim conforme ADR-0013 : pas de logique métier ici, délégation au
 * repository d'écriture.
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
