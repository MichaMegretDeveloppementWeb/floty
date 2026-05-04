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
 *      - `lifetime`       : cumul tous exercices (4 KPIs phares de la fiche)
 *      - `history`        : récap tabulaire exercice par exercice
 *      - `activityByYear` : détail visuel par exercice (heatmap mensuelle
 *                           + top 3 véhicules), pré-calculé pour toutes
 *                           les années dans `availableYears` afin que le
 *                           sélecteur local côté front ne déclenche aucun
 *                           aller-retour réseau (chantier K L2)
 *   3. **Drivers** : liste pour l'onglet « Conducteurs ».
 *
 * `availableYears` peuple le sélecteur d'année **local** de la section
 * Activité. `currentRealYear` est l'année calendaire réelle (séparée
 * du sélecteur), exposée notamment au tableau historique pour marquer
 * l'exercice en cours.
 */
#[TypeScript]
final class CompanyDetailData extends Data
{
    /**
     * @param  list<CompanyDriverRowData>  $drivers
     * @param  list<CompanyYearStatsData>  $history  Un objet par exercice avec ≥ 1 contrat
     * @param  list<CompanyActivityYearData>  $activityByYear  Détail visuel par exercice (1 entrée par année dans `availableYears`)
     * @param  list<int>  $availableYears  Années avec ≥ 1 contrat — peuple le sélecteur de la section Activité
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
        #[DataCollectionOf(CompanyActivityYearData::class)]
        public array $activityByYear,
        public array $availableYears,
        public int $currentRealYear,
    ) {}
}
