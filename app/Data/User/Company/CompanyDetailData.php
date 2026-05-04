<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue détaillée d'une entreprise — alimente la page Show Company
 * (chantier K, refonte fiche entreprise, ADR-0020 D3).
 *
 * Le DTO porte trois familles d'information :
 *   1. **Identité** (intemporelle) : nom, SIREN, adresse, contact, statuts.
 *   2. **Stats temporelles** :
 *      - `lifetime` : cumul tous exercices (section « Depuis le début »)
 *      - `byYear`   : exercice sélectionné via `?year=` (section « Aperçu par année »)
 *      - `history`  : récap exercice par exercice (section « Historique par année »)
 *   3. **Drivers** : liste pour l'onglet « Conducteurs ».
 *
 * `availableYears` peuple le sélecteur local de la fiche.
 * `currentRealYear` est l'année calendaire réelle (séparée de l'année
 * sélectionnée), exposée pour les sections « temps réel » des lots
 * ultérieurs (cf. chantier K § Lots ultérieurs).
 */
#[TypeScript]
final class CompanyDetailData extends Data
{
    /**
     * @param  list<CompanyDriverRowData>  $drivers
     * @param  list<CompanyYearStatsData>  $history  Un objet par exercice avec ≥ 1 contrat
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
        public CompanyLifetimeStatsData $lifetime,
        #[DataCollectionOf(CompanyYearStatsData::class)]
        public array $history,
        public int $currentRealYear,
    ) {}
}
