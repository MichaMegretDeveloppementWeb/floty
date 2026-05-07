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
 *  - `summaryDays` / `summaryTax` : agrégat ligne (jours + montant
 *    taxe) cohérent avec le scope courant.
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
    summaryTax: number;
    exitDate: string | null;
    weeksWithUnavailability: number[];
};
