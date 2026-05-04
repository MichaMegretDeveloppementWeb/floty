<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue détaillée d'une entreprise - utilisée par la page Show Company
 * avec onglets (Phase 06 L4).
 */
#[TypeScript]
final class CompanyDetailData extends Data
{
    /**
     * @param  list<CompanyDriverRowData>  $drivers
     */
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
        public bool $isActive,
        public bool $isOig,
        public bool $isIndividualBusiness,
        public int $contractsCount,
        public int $activeDriversCount,
        public int $totalDriversCount,
        #[DataCollectionOf(CompanyDriverRowData::class)]
        public array $drivers,
    ) {}
}
