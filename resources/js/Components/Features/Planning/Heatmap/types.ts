/**
 * Shared types for the planning Heatmap component.
 * HeatmapVehicleView is the unified row shape normalized by Heatmap.vue,
 * abstracting the difference between the global and per-company DTOs.
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
    /** Annual tax due (EUR); null until realCosts has responded. */
    summaryTax: number | null;
    exitDate: string | null;
    weeksWithUnavailability: number[];
    /** Theoretical full-year tax (EUR); null until fullYearCosts has responded. */
    fullYearTax: number | null;
    /** Daily proration (EUR/day); null until fullYearCosts has responded. */
    dailyTaxRate: number | null;
    /** Daily/weekly/monthly pricings (cents) for the current year; null if unset. */
    dailyRateCents: number | null;
    weeklyRateCents: number | null;
    monthlyRateCents: number | null;
};

/**
 * vehicleId -> theoretical full-year costs map, served via Inertia::defer
 * "fast" group (fed from the persistent fiscal cache).
 */
export type HeatmapFullYearCosts = Record<
    number,
    { fullYearTax: number; dailyTaxRate: number }
>;

/**
 * vehicleId -> actual annual tax due map, served via Inertia::defer
 * "slow" group. Scope-aware (global on the fleet view, company on the
 * by-company view).
 */
export type HeatmapRealCosts = Record<number, { annualTaxDue: number }>;

/**
 * month [1..12] -> totalCents (net rental after discounts), served via
 * Inertia::defer "rentals" group. null per month signals at least one
 * unpriced vehicle in the per-company view.
 */
export type HeatmapMonthlyRentals = Record<number, number | null>;
