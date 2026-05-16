/**
 * Types partagés du composant Heatmap planning.
 *
 * `HeatmapVehicleView` : shape unifiée d'une ligne véhicule, dérivée
 * d'un DTO Vue d'ensemble (`PlanningHeatmapVehicleData`) ou d'un DTO
 * Vue Entreprise (`PlanningHeatmapCompanyVehicleData`). Le composant
 * Heatmap.vue normalise via un computed avant de passer aux partials,
 * pour qu'ils restent simples (pas de branching mode).
 *
 *  - `weeksForColor` : densité utilisée pour `densityClass` (couleur
 *    de cellule). En Vue d'ensemble = densité globale ; en Vue
 *    Entreprise = densité globale (signal de disponibilité).
 *  - `weeksForCount` : densité utilisée pour le chiffre affiché.
 *    En Vue d'ensemble = densité globale ; en Vue Entreprise = densité
 *    scopée à l'entreprise sélectionnée.
 *  - `summaryDays` : agrégat ligne (jours) cohérent avec le scope courant.
 *  - `summaryTax` / `fullYearTax` / `dailyTaxRate` : montants fiscaux
 *    servis en `Inertia::defer` · `null` tant que la 2ᵉ RTT n'a pas
 *    répondu (chantier perf 2026-05-16). Les partials affichent un
 *    skeleton inline quand `null`.
 */

export type HeatmapVehicleView = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
    userType: App.Enums.Vehicle.VehicleUserType;
    energy: App.Enums.Vehicle.EnergySource;
    co2Method: App.Enums.Vehicle.HomologationMethod;
    co2Value: number | null;
    taxableHorsepower: number | null;
    weeksForColor: number[];
    weeksForCount: number[];
    summaryDays: number;
    /** Taxe annuelle due (€) · null tant que `costs` n'a pas répondu. */
    summaryTax: number | null;
    exitDate: string | null;
    weeksWithUnavailability: number[];
    /** Taxe pleine annuelle théorique € · null tant que `costs` n'a pas répondu. */
    fullYearTax: number | null;
    /** Prorata journalier (€/jour) · null tant que `costs` n'a pas répondu. */
    dailyTaxRate: number | null;
};

/**
 * Map `vehicleId → coûts fiscaux` servie en `Inertia::defer` (chantier
 * perf 2026-05-16). `undefined` au mount initial puis hydratée à la 2ᵉ
 * RTT. Une entrée par véhicule actif sur l'année courante.
 */
export type HeatmapCosts = Record<
    number,
    { annualTaxDue: number; fullYearTax: number; dailyTaxRate: number }
>;
