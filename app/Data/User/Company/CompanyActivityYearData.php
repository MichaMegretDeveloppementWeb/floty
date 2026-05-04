<?php

declare(strict_types=1);

namespace App\Data\User\Company;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Détail d'activité d'une entreprise pour un exercice — alimente la
 * section « Activité » de la fiche entreprise (chantier K L2,
 * ADR-0020 D3) avec deux visualisations complémentaires :
 *
 *   1. **Heatmap mensuelle** (`daysByMonth`) : 12 entrées, 1 par mois
 *      ISO (janvier = index 0, décembre = index 11). Chaque entier est
 *      la somme des jours-véhicules occupés par l'entreprise sur le
 *      mois (un véhicule × 1 jour = 1).
 *   2. **Top véhicules** (`topVehicles`) : les 3 véhicules les plus
 *      attribués à l'entreprise sur l'année, triés desc. Liste vide
 *      si aucun contrat sur l'année.
 *
 * Le service backend pré-calcule cet objet pour **toutes les années**
 * disponibles dans `CompanyDetailData::$availableYears` ; le sélecteur
 * d'année local côté front sélectionne ensuite l'entrée à afficher
 * sans aller-retour réseau.
 */
#[TypeScript]
final class CompanyActivityYearData extends Data
{
    /**
     * @param  list<int>  $daysByMonth  Exactement 12 entrées (janv..déc)
     * @param  list<CompanyTopVehicleData>  $topVehicles  Max 3, triées desc par daysUsed
     */
    public function __construct(
        public int $year,
        public array $daysByMonth,
        #[DataCollectionOf(CompanyTopVehicleData::class)]
        public array $topVehicles,
    ) {}
}
