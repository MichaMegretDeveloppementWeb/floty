<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use App\Data\Shared\YearScopeData;
use App\Enums\Company\CompanyColor;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Vue détaillée d'une entreprise — alimente la page Show Company.
 *
 * **Doctrine temporelle (chantier η Phase 1, 2026-05-05)** : 3 lentilles
 * temporelles distinctes pour la fiche :
 *
 *   1. **Présent** (KPIs en haut) : champs `kpiYear` / `kpiStats` /
 *      `kpiFiscalAvailable`. Reflètent **uniquement l'année calendaire
 *      courante** (ex. 2026 aujourd'hui), pas pilotables. Si pas de
 *      données → 0/—. Si pas de règles fiscales codées → message
 *      explicite (`kpiFiscalAvailable = false`).
 *   2. **Évolution** (section Historique) : `history[]` filtré sur
 *      `year < kpiYear` (toutes années passées avec contrats, sans
 *      l'année courante qui est déjà dans les KPIs). Pas de doublon.
 *   3. **Exploration** (section Activité) : `activityByYear[]` détail
 *      visuel par exercice (heatmap + top véhicules). Sélecteur local
 *      piloté par `yearScope` qui expose les bornes globales (calculées
 *      par `AvailableYearsResolver` — ADR-0020).
 *
 * **Identité** (intemporelle) : nom, SIREN, adresse, contact, statuts —
 * pas concernée par la doctrine temporelle.
 *
 * **`yearScope`** porte les bornes globales `[minYear, …, max]` calculées
 * dynamiquement depuis les contrats actifs (Phase 0.1). Remplace l'ancien
 * `availableYears` par-entreprise (qui restait limité aux années où
 * **cette** entreprise avait un contrat — décision HD4 : on uniformise
 * à l'échelle globale pour cohérence UX entre fiches).
 *
 * **`lifetime`** est conservé pour ne pas casser les consommateurs
 * potentiels résiduels — sera retiré en Phase 5 cleanup si effectivement
 * inutilisé après refonte de tous les onglets.
 */
#[TypeScript]
final class CompanyDetailData extends Data
{
    /**
     * @param  list<CompanyDriverRowData>  $drivers
     * @param  list<CompanyYearStatsData>  $history  Un objet par exercice **passé** avec ≥ 1 contrat (exclut l'année courante qui est dans `kpiStats`)
     * @param  list<CompanyActivityYearData>  $activityByYear  Détail visuel par exercice (1 entrée par année dans `availableYears` historiques)
     * @param  list<int>  $availableYears  Années avec ≥ 1 contrat sur cette entreprise spécifique — alimente la section Activité
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
        public CompanyYearStatsData $kpiStats,
        public int $kpiYear,
        public bool $kpiFiscalAvailable,
        #[DataCollectionOf(CompanyYearStatsData::class)]
        public array $history,
        #[DataCollectionOf(CompanyActivityYearData::class)]
        public array $activityByYear,
        public array $availableYears,
        public int $currentRealYear,
        public YearScopeData $yearScope,
    ) {}
}
