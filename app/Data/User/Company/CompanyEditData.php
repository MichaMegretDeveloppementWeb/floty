<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue slim d'une entreprise · alimente UNIQUEMENT la page Edit
 * (formulaire d'identité / adresse / contact).
 *
 * **Pourquoi ce DTO existe** · la page Edit consomme seulement 14
 * champs scalaires, mais le DTO `CompanyDetailData` (utilisé par Show)
 * embarque drivers + lifetime + history + activityByYear qui
 * déclenchent le pipeline fiscal complet (~280 ms cold). `CompanyEditData`
 * permet à `CompanyController::edit()` d'éviter tout ce calcul inutile
 * en utilisant `CompanyDetailService::detailForEdit()`.
 *
 * Doctrine `implementation-rules/chargement-strict-par-ecran.md` § 2
 * (un DTO par usage, jamais de DTO "complet" imposé à tous).
 */
#[TypeScript]
final class CompanyEditData extends Data
{
    public function __construct(
        public int $id,
        public string $legalName,
        public string $shortCode,
        public CompanyColor $color,
        public ?string $siren,
        public ?string $siret,
        public ?string $addressLine1,
        public ?string $addressLine2,
        public ?string $postalCode,
        public ?string $city,
        public string $country,
        public ?string $contactName,
        public ?string $contactEmail,
        public ?string $contactPhone,
    ) {}
}
