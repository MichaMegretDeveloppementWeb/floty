import { computed } from 'vue';
import type { ComputedRef } from 'vue';

type Segment = App.Data.User.Vehicle.VehicleWeekSegmentData;
type Stats = App.Data.User.Vehicle.VehicleUsageStatsData;
type CompanyEntry = App.Data.User.Vehicle.VehicleCompanyUsageData;

type MonthLabel = { name: string; weeks: number };

/**
 * Données et helpers pour la timeline annuelle 52 semaines
 * (segments d'attribution + indispo empilées). Le tableau
 * `monthLabels` suit la convention du design system
 * (4-4-5-4-4-5-4-4-5-4-4-5 = 52) cohérente avec la heatmap planning.
 */
export function useVehicleYearlyUsageTimeline(props: { stats: Stats }): {
    monthLabels: readonly MonthLabel[];
    totalVehicleDays: ComputedRef<number>;
    legendEntries: ComputedRef<CompanyEntry[]>;
    heightForDays: (days: number) => string;
    heightFor: (segment: Segment) => string;
} {
    const monthLabels: readonly MonthLabel[] = [
        { name: 'Jan', weeks: 4 },
        { name: 'Fév', weeks: 4 },
        { name: 'Mar', weeks: 5 },
        { name: 'Avr', weeks: 4 },
        { name: 'Mai', weeks: 4 },
        { name: 'Juin', weeks: 5 },
        { name: 'Juil', weeks: 4 },
        { name: 'Août', weeks: 4 },
        { name: 'Sept', weeks: 5 },
        { name: 'Oct', weeks: 4 },
        { name: 'Nov', weeks: 4 },
        { name: 'Déc', weeks: 5 },
    ];

    const totalVehicleDays = computed<number>(() =>
        props.stats.weeklyBreakdown.reduce((sum, w) => sum + w.totalDays, 0),
    );

    const legendEntries = computed<CompanyEntry[]>(() => props.stats.companies);

    const heightForDays = (days: number): string => `${(days / 7) * 100}%`;
    const heightFor = (segment: Segment): string => heightForDays(segment.days);

    return {
        monthLabels,
        totalVehicleDays,
        legendEntries,
        heightForDays,
        heightFor,
    };
}
