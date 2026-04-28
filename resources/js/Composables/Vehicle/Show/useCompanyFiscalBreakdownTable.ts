import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { DataTableColumn } from '@/types/ui';

type Row = App.Data.User.Vehicle.VehicleCompanyUsageData;

/**
 * Configuration colonnes + agrégats footer pour le tableau
 * « Répartition fiscale par entreprise utilisatrice ». Les
 * helpers visuels (initiales pour CompanyTag) sont aussi exposés.
 */
export function useCompanyFiscalBreakdownTable(props: {
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}): {
    columns: readonly DataTableColumn<Row>[];
    totalDays: ComputedRef<number>;
    totalProrato: ComputedRef<number>;
    totalCo2: ComputedRef<number>;
    totalPollutants: ComputedRef<number>;
    totalAll: ComputedRef<number>;
    initialsOf: (shortCode: string) => string;
} {
    const columns: readonly DataTableColumn<Row>[] = [
        { key: 'shortCode', label: 'Entreprise' },
        { key: 'daysUsed', label: 'Jours', align: 'right', mono: true },
        { key: 'proratoPercent', label: 'Prorata', align: 'right', mono: true },
        { key: 'taxCo2', label: 'Taxe CO₂', align: 'right', mono: true },
        { key: 'taxPollutants', label: 'Taxe polluant', align: 'right', mono: true },
        { key: 'taxTotal', label: 'Total', align: 'right', mono: true },
    ];

    const totalDays = computed<number>(() =>
        props.stats.companies.reduce((sum, c) => sum + c.daysUsed, 0),
    );

    const totalProrato = computed<number>(() =>
        props.stats.companies.reduce((sum, c) => sum + c.proratoPercent, 0),
    );

    const totalCo2 = computed<number>(() =>
        props.stats.companies.reduce((sum, c) => sum + c.taxCo2, 0),
    );

    const totalPollutants = computed<number>(() =>
        props.stats.companies.reduce((sum, c) => sum + c.taxPollutants, 0),
    );

    const totalAll = computed<number>(() =>
        props.stats.companies.reduce((sum, c) => sum + c.taxTotal, 0),
    );

    const initialsOf = (shortCode: string): string =>
        shortCode.slice(0, 2).toUpperCase();

    return {
        columns,
        totalDays,
        totalProrato,
        totalCo2,
        totalPollutants,
        totalAll,
        initialsOf,
    };
}
