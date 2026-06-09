/**
 * Server-side Index table for Vehicles (ADR-0020).
 *
 * - `includeExited` (boolean) + `status` (VehicleStatus | null) filters
 * - `fullYearTax` and `rentalPriceFullYear` columns rendered but NOT sortable
 *   (computed by the fiscal aggregator and billing module)
 * - Page-local year selector driving only the financial columns. Managed as a `year` filter in
 *   `useServerTableState` for free URL serialisation + Inertia partial reload (each year change
 *   triggers a backend recompute of `fullYearTax`).
 *
 * Rendering stays in `FleetTable.vue` (cell-* slots).
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import type { DataTableColumn } from '@/types/ui';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

export type FleetSortKey =
    | 'licensePlate'
    | 'model'
    | 'firstFrenchRegistrationDate'
    | 'acquisitionDate'
    | 'currentStatus';

// UI column key → backend sortKey (VehicleIndexQueryData whitelist).
// `fullYearTax` and `rentalPriceFullYear` have no entry because they are not sortable.
const COLUMN_TO_SORT_KEY: Partial<Record<string, FleetSortKey>> = {
    licensePlate: 'licensePlate',
    model: 'model',
    firstFrenchRegistrationDate: 'firstFrenchRegistrationDate',
};

export type FleetFilters = {
    status: App.Enums.Vehicle.VehicleStatus | null;
    includeExited: boolean;
    energySource: App.Enums.Vehicle.EnergySource | null;
    pollutantCategory: App.Enums.Vehicle.PollutantCategory | null;
    handicapAccess: boolean | null;
    firstRegistrationYearMin: number | null;
    firstRegistrationYearMax: number | null;
    /** Keep only vehicles with at least one control needing attention today. */
    controlsDue: boolean | null;
    /** Year for the financial columns (Taxe pleine, Montant loyer). */
    year: number;
};

export function useFleetTable(opts: {
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
    /** Backend-resolved year (selectedYear), used as fallback. */
    selectedYear: number;
    /** Real year (resolver year): URL is omitted when on it. */
    currentRealYear: number;
}): {
    columns: ComputedRef<readonly DataTableColumn<VehicleRow>[]>;
    state: ServerTableState<FleetFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: VehicleRow) => void;
} {
    const state = useServerTableState<FleetFilters>({
        // controlsBadges is eager and keyed by the visible vehicles, so it
        // reloads with them.
        only: ['vehicles', 'query', 'selectedYear', 'controlsBadges'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            status: null,
            // Default true: exited vehicles are visible (retroactive read/edit). Uncheck to hide.
            includeExited: true,
            energySource: null,
            pollutantCategory: null,
            handicapAccess: null,
            firstRegistrationYearMin: null,
            firstRegistrationYearMax: null,
            controlsDue: null,
            year: opts.currentRealYear,
        },
        initialFilters: {
            status: opts.query.status,
            includeExited: opts.query.includeExited,
            energySource: opts.query.energySource,
            pollutantCategory: opts.query.pollutantCategory,
            handicapAccess: opts.query.handicapAccess,
            firstRegistrationYearMin: opts.query.firstRegistrationYearMin,
            firstRegistrationYearMax: opts.query.firstRegistrationYearMax,
            controlsDue: opts.query.controlsDue,
            year: opts.selectedYear,
        },
        serializeFilters: (f) => ({
            status: f.status,
            // includeExited backend default is `true`: only send to URL when user unchecked (override = 0).
            includeExited: f.includeExited ? null : 0,
            energySource: f.energySource,
            pollutantCategory: f.pollutantCategory,
            handicapAccess: f.handicapAccess === true ? 1 : null,
            firstRegistrationYearMin: f.firstRegistrationYearMin,
            firstRegistrationYearMax: f.firstRegistrationYearMax,
            controlsDue: f.controlsDue === true ? 1 : null,
            // Real year stays implicit (clean URL); serialise only non-trivial values.
            year: f.year === opts.currentRealYear ? null : f.year,
        }),
    });

    // Year-dependent labels recompute automatically when `state.filters.value.year` changes.
    const columns = computed<readonly DataTableColumn<VehicleRow>[]>(() => {
        const year = state.filters.value.year;

        return [
            { key: 'licensePlate', label: 'Immatriculation' },
            { key: 'model', label: 'Modèle' },
            { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
            {
                key: 'fullYearTax',
                label: `Taxe pleine ${year}`,
                align: 'right',
            },
            {
                key: 'rentalPriceFullYear',
                label: `Montant loyer ${year}`,
                align: 'right',
            },
        ];
    });

    const activeSortColumnKey = computed<string | null>(() => {
        if (state.sort.value.key === null) {
            return null;
        }

        const entry = Object.entries(COLUMN_TO_SORT_KEY).find(
            ([, sortKey]) => sortKey === state.sort.value.key,
        );

        return entry ? entry[0] : null;
    });

    function onHeaderClick(columnKey: string): void {
        const sortKey = COLUMN_TO_SORT_KEY[columnKey];

        if (sortKey !== undefined) {
            state.setSort(sortKey);
        }
        // fullYearTax has no mapping → no-op (non-interactive header).
    }

    function onRowClick(row: VehicleRow): void {
        router.visit(vehiclesShowRoute.url({ vehicle: row.id }));
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        onHeaderClick,
        onRowClick,
    };
}
