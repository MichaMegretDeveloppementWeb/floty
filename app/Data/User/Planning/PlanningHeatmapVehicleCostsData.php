<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Coûts fiscaux d'un véhicule sur l'année courante du planning · servis
 * en `Inertia::defer` car leur calcul exécute le pipeline fiscal complet
 * (~10 ms × N véhicules ≈ ~630 ms sur 64 véhicules en cold).
 *
 * Le DTO `PlanningHeatmapVehicleData` (et sa variante Company) reste
 * eager au mount avec les seules données « cheap » (densité semaines,
 * métadonnées véhicule). Cette map vient hydrater les 2 cellules
 * monétaires (« Taxe pleine » à gauche + « €XXXX · N j » à droite) une
 * fois la 2ᵉ RTT terminée.
 *
 * `annualTaxDue` reflète le **scope courant** ·
 *   - Vue d'ensemble · taxe annuelle globale du véhicule (toutes
 *     entreprises confondues).
 *   - Vue Entreprise · taxe annuelle scopée à l'entreprise sélectionnée
 *     (== ancien `annualTaxDueForCompany`).
 */
#[TypeScript]
final class PlanningHeatmapVehicleCostsData extends Data
{
    public function __construct(
        public float $annualTaxDue,
        /** Taxe pleine annuelle théorique (€) pour le véhicule à 100 % d'utilisation. */
        public float $fullYearTax,
        /** Prorata journalier = `fullYearTax / daysInYear` (€/jour). */
        public float $dailyTaxRate,
    ) {}
}
