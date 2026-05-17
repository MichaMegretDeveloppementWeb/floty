<?php

declare(strict_types=1);

namespace App\Data\User\Planning;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Coûts fiscaux **théoriques pleine année** d'un véhicule · servis en
 * `Inertia::defer` group « fast » sur les pages Planning (chantier perf
 * Étape 3 · 2026-05-17).
 *
 * **Pourquoi un DTO séparé** · le calcul `vehicleFullYearTaxBreakdown`
 * (source de `fullYearTax` + `dailyTaxRate`) bénéficie d'une mémoïsation
 * per-request et reste rapide. Le séparer de la taxe annuelle due réelle
 * (`PlanningHeatmapVehicleRealCostsData`, ~3-5 ms / véhicule) permet à
 * la cellule « Taxe pleine » à gauche de la heatmap d'apparaître très
 * rapidement, indépendamment de la cellule « €XXXX · N j » à droite
 * (taxe annuelle due) qui prend plus de temps.
 *
 * **Indépendance du scope entreprise** · ces valeurs sont THÉORIQUES
 * 100 % usage · identiques en Vue d'ensemble et en Vue Entreprise.
 */
#[TypeScript]
final class PlanningHeatmapVehicleFullYearCostsData extends Data
{
    public function __construct(
        /** Taxe pleine annuelle théorique (€) pour le véhicule à 100 % d'utilisation. */
        public float $fullYearTax,
        /** Prorata journalier = `fullYearTax / daysInYear` (€/jour). */
        public float $dailyTaxRate,
    ) {}
}
