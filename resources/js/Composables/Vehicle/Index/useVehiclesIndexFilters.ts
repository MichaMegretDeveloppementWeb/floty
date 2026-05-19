/**
 * `v-model` wrappers around `useFleetTable` filters for the Vehicles Index Filters popover
 * (SelectInput / CheckboxInput / YearRangeGridPicker), plus option arrays and active filter counter.
 *
 * Carries no `ref()` of its own: acts purely as a typed façade over `tableState.filters`.
 * Tests cover the counter and String ↔ Enum conversions; the rest is covered by backend
 * `VehicleControllerTest` Feature tests.
 */

import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import type { FleetFilters } from '@/Composables/Vehicle/Index/useFleetTable';
import {
    energySourceLabel,
    pollutantCategoryLabel,
    vehicleStatusLabel,
} from '@/Utils/labels/vehicleEnumLabels';

export type SelectOption = { value: string | number; label: string };
export type YearOption = { value: number; label: string };

export type UseVehiclesIndexFiltersOptions = {
    tableState: ServerTableState<FleetFilters>;
    /** Available years for the financial year selector. */
    availableYears: ComputedRef<readonly number[]>;
};

export type UseVehiclesIndexFiltersReturn = {
    // v-model filters
    searchModel: ComputedRef<string>;
    selectedYearModel: ComputedRef<number>;
    statusModel: ComputedRef<string | number>;
    energySourceModel: ComputedRef<string | number>;
    pollutantCategoryModel: ComputedRef<string | number>;
    handicapAccessModel: ComputedRef<boolean>;
    firstRegistrationYearMinModel: ComputedRef<number | null>;
    firstRegistrationYearMaxModel: ComputedRef<number | null>;
    includeExitedModel: ComputedRef<boolean>;

    // Static option arrays (computed for symmetry with yearOptions)
    statusOptions: SelectOption[];
    energySourceOptions: SelectOption[];
    pollutantCategoryOptions: SelectOption[];
    yearOptions: ComputedRef<YearOption[]>;

    // Active filters counter (popover badge)
    activeFiltersCount: ComputedRef<number>;
};

export function useVehiclesIndexFilters(
    options: UseVehiclesIndexFiltersOptions,
): UseVehiclesIndexFiltersReturn {
    const { tableState, availableYears } = options;

    // Static option arrays (Enum → label).

    const statusOptions: SelectOption[] = [
        { value: '', label: 'Tous les statuts' },
        ...(Object.keys(vehicleStatusLabel) as App.Enums.Vehicle.VehicleStatus[]).map(
            (value) => ({ value, label: vehicleStatusLabel[value] }),
        ),
    ];

    const energySourceOptions: SelectOption[] = [
        { value: '', label: 'Toutes les énergies' },
        ...(Object.keys(energySourceLabel) as App.Enums.Vehicle.EnergySource[]).map(
            (value) => ({ value, label: energySourceLabel[value] }),
        ),
    ];

    const pollutantCategoryOptions: SelectOption[] = [
        { value: '', label: 'Toutes catégories' },
        ...(
            Object.keys(
                pollutantCategoryLabel,
            ) as App.Enums.Vehicle.PollutantCategory[]
        ).map((value) => ({ value, label: pollutantCategoryLabel[value] })),
    ];

    const yearOptions = computed<YearOption[]>(() =>
        availableYears.value.map((year) => ({ value: year, label: String(year) })),
    );

    // v-model wrappers (typed façade over tableState.filters).

    const searchModel = computed<string>({
        get: () => tableState.search.value,
        set: (value: string) => {
            tableState.search.value = value;
        },
    });

    const selectedYearModel = computed<number>({
        get: () => tableState.filters.value.year,
        set: (value: number) => {
            tableState.setFilter('year', value);
        },
    });

    const statusModel = computed<string | number>({
        get: () => tableState.filters.value.status ?? '',
        set: (value: string | number) => {
            const v = String(value);
            const isValid =
                v === 'active' ||
                v === 'maintenance' ||
                v === 'sold' ||
                v === 'destroyed' ||
                v === 'other';
            tableState.setFilter(
                'status',
                isValid ? (v as App.Enums.Vehicle.VehicleStatus) : null,
            );
        },
    });

    const energySourceModel = computed<string | number>({
        get: () => tableState.filters.value.energySource ?? '',
        set: (value: string | number) => {
            const v = String(value);
            const allowed = Object.keys(
                energySourceLabel,
            ) as App.Enums.Vehicle.EnergySource[];
            const next = allowed.includes(v as App.Enums.Vehicle.EnergySource)
                ? (v as App.Enums.Vehicle.EnergySource)
                : null;
            tableState.setFilter('energySource', next);
        },
    });

    const pollutantCategoryModel = computed<string | number>({
        get: () => tableState.filters.value.pollutantCategory ?? '',
        set: (value: string | number) => {
            const v = String(value);
            const allowed = Object.keys(
                pollutantCategoryLabel,
            ) as App.Enums.Vehicle.PollutantCategory[];
            const next = allowed.includes(v as App.Enums.Vehicle.PollutantCategory)
                ? (v as App.Enums.Vehicle.PollutantCategory)
                : null;
            tableState.setFilter('pollutantCategory', next);
        },
    });

    const handicapAccessModel = computed<boolean>({
        get: () => tableState.filters.value.handicapAccess === true,
        set: (value: boolean) => {
            tableState.setFilter('handicapAccess', value === true ? true : null);
        },
    });

    const firstRegistrationYearMinModel = computed<number | null>({
        get: () => tableState.filters.value.firstRegistrationYearMin,
        set: (value: number | null) => {
            tableState.setFilter('firstRegistrationYearMin', value);
        },
    });

    const firstRegistrationYearMaxModel = computed<number | null>({
        get: () => tableState.filters.value.firstRegistrationYearMax,
        set: (value: number | null) => {
            tableState.setFilter('firstRegistrationYearMax', value);
        },
    });

    const includeExitedModel = computed<boolean>({
        get: () => tableState.filters.value.includeExited,
        set: (value: boolean) => {
            tableState.setFilter('includeExited', value);
        },
    });

    // Active filters counter (popover badge).

    const activeFiltersCount = computed<number>(() => {
        let n = 0;
        const f = tableState.filters.value;

        if (f.status !== null) {
            n += 1;
        }

        // includeExited default true: counted as active only when the user explicitly unchecked.
        if (!f.includeExited) {
            n += 1;
        }

        if (f.energySource !== null) {
            n += 1;
        }

        if (f.pollutantCategory !== null) {
            n += 1;
        }

        if (f.handicapAccess === true) {
            n += 1;
        }

        if (
            f.firstRegistrationYearMin !== null ||
            f.firstRegistrationYearMax !== null
        ) {
            n += 1;
        }

        return n;
    });

    return {
        searchModel,
        selectedYearModel,
        statusModel,
        energySourceModel,
        pollutantCategoryModel,
        handicapAccessModel,
        firstRegistrationYearMinModel,
        firstRegistrationYearMaxModel,
        includeExitedModel,
        statusOptions,
        energySourceOptions,
        pollutantCategoryOptions,
        yearOptions,
        activeFiltersCount,
    };
}
