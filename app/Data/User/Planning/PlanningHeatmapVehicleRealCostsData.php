<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Coûts fiscaux **réels selon usage** d'un véhicule · servis en
 * `Inertia::defer` group « slow » sur les pages Planning (chantier
 * perf Étape 3 · 2026-05-17).
 *
 * **Pourquoi séparé du DTO full-year** · `vehicleAnnualTax` n'est PAS
 * cachée (dépendrait des contrats + indispos + scope · invalidation
 * complexe). Le séparer permet à la heatmap de ne pas pénaliser
 * l'affichage de la « Taxe pleine » (caché, rapide) avec le calcul
 * plus lent (~3-5 ms / véhicule = ~200 ms sur 64 véhicules).
 *
 * **Reflète le scope courant** ·
 *   - Vue d'ensemble · taxe annuelle globale (toutes entreprises).
 *   - Vue Entreprise · taxe annuelle scopée à l'entreprise sélectionnée
 *     (== ancien `annualTaxDueForCompany`).
 */
#[TypeScript]
final class PlanningHeatmapVehicleRealCostsData extends Data
{
    public function __construct(
        /** Taxe annuelle due (€) selon usage réel sur l'année courante. */
        public float $annualTaxDue,
    ) {}
}
