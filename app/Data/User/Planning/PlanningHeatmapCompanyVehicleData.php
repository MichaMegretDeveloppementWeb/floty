<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use App\Enums\Vehicle\EnergySource;
use App\Enums\Vehicle\HomologationMethod;
use App\Enums\Vehicle\VehicleUserType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Une ligne de la heatmap Vue Entreprise (chantier P1) - un véhicule sur
 * 52 semaines, avec densité globale (couleur) et densité scopée à
 * l'entreprise sélectionnée (chiffre affiché).
 *
 * Différence avec {@see PlanningHeatmapVehicleData} (Vue d'ensemble) :
 *   - `weeksGlobal` (toutes entreprises) pilote la **couleur** de la
 *     cellule = signal de disponibilité du véhicule.
 *   - `weeksForCompany` (entreprise sélectionnée) pilote le **chiffre**
 *     affiché = jours utilisés par cette entreprise précise.
 *   - `daysTotalForCompany` et `annualTaxDueForCompany` agrègent
 *     uniquement la part de l'entreprise (pas le total flotte).
 */
#[TypeScript]
final class PlanningHeatmapCompanyVehicleData extends Data
{
    /**
     * @param  list<int>  $weeksGlobal  52 entiers (0-7) - densité globale
     *                                  toutes entreprises confondues, pilote
     *                                  la couleur de la cellule.
     * @param  list<int>  $weeksForCompany  52 entiers (0-7) - densité scopée
     *                                      à l'entreprise sélectionnée, pilote
     *                                      le chiffre affiché.
     * @param  list<int>  $weeksWithUnavailability  numéros ISO (1-52) des
     *                                              semaines portant au moins
     *                                              un jour d'indispo (ADR-0019
     *                                              § 2 D5).
     */
    public function __construct(
        public int $id,
        public string $licensePlate,
        public string $brand,
        public string $model,
        public VehicleUserType $userType,
        public EnergySource $energy,
        public HomologationMethod $co2Method,
        public ?int $co2Value,
        public ?int $taxableHorsepower,
        public array $weeksGlobal,
        public array $weeksForCompany,
        public int $daysTotalForCompany,
        public float $annualTaxDueForCompany,
        public ?string $exitDate,
        public array $weeksWithUnavailability,
    ) {}
}
