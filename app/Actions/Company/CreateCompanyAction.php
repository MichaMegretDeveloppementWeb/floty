<?php

declare(strict_types=1);

namespace App\Actions\Company;

use App\Contracts\Repositories\User\Company\CompanyReadRepositoryInterface;
use App\Contracts\Repositories\User\Company\CompanyWriteRepositoryInterface;
use App\Data\User\Company\StoreCompanyData;
use App\Exceptions\Company\CompanyShortCodeCollisionException;
use App\Models\Company;

/**
 * Création d'une entreprise avec génération automatique du code court à
 * partir du nom légal (cf. chantier A V1.2).
 *
 * Le code court n'étant plus saisi par l'utilisateur, on le calcule via
 * Company::generateShortCode() et on vérifie l'unicité avant de déléguer
 * au repository d'écriture. En cas de collision (UNIQUE constraint), on
 * lève une exception qui sera convertie en ValidationException sur le
 * champ legal_name côté Controller.
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
