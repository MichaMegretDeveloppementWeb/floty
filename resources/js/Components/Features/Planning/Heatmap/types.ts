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
 *    servis en `Inertia::defer` dans 2 props séparées (chantier perf
 *    Étape 3 · 2026-05-17) ·
 *      - `fullYearTax` + `dailyTaxRate` arrivent rapidement (cachés)
 *      - `summaryTax` arrive plus tard (non caché)
 *    `null` tant que la prop correspondante n'a pas répondu · les
 *    partials affichent un skeleton inline.
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
    /** Taxe annuelle due (€) · null tant que `realCosts` n'a pas répondu. */
    summaryTax: number | null;
    exitDate: string | null;
    weeksWithUnavailability: number[];
    /** Taxe pleine annuelle théorique € · null tant que `fullYearCosts` n'a pas répondu. */
    fullYearTax: number | null;
    /** Prorata journalier (€/jour) · null tant que `fullYearCosts` n'a pas répondu. */
    dailyTaxRate: number | null;
    /**
     * Tarifs jour/semaine/mois (cents) pour l'année courante · `null` si
     * aucun tarif saisi pour ce véhicule cette année. Eager (3 ints en
     * batch SQL plat, coût négligeable · doctrine chargement-strict-par-ecran).
     */
    dailyRateCents: number | null;
    weeklyRateCents: number | null;
    monthlyRateCents: number | null;
};

/**
 * Map `vehicleId → coûts pleine année théorique` servie en
 * `Inertia::defer` group "fast" (chantier perf Étape 3 · 2026-05-17).
 * Hits warm très rapides grâce au cache fiscal persistant.
 */
export type HeatmapFullYearCosts = Record<
    number,
    { fullYearTax: number; dailyTaxRate: number }
>;

/**
 * Map `vehicleId → coût annuel dû réel` servie en `Inertia::defer`
 * group "slow" (chantier perf Étape 3). Non cachée · ~3-5 ms / véhicule.
 * Reflète le scope courant · global en Vue d'ensemble, scopé en Vue
 * Entreprise.
 */
export type HeatmapRealCosts = Record<number, { annualTaxDue: number }>;

/**
 * Map `month [1..12] → totalCents` (loyer net post-réductions) servie en
 * `Inertia::defer` group "rentals" (SC1 · 2026-05-18). Affichée sous
 * chaque label de mois dans l'entête de la heatmap.
 *
 * `null` par mois = vue entreprise avec au moins un véhicule sans tarif
 * ce mois-là (signal user). En vue fleet, les mois partiels sont déjà
 * agrégés (skip silencieux des cies sans tarif, jamais `null`).
 */
export type HeatmapMonthlyRentals = Record<number, number | null>;
