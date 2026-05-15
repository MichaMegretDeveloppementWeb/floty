/**
 * Wrappers `v-model` autour des filtres `useFleetTable` pour les
 * SelectInput / CheckboxInput / YearRangeGridPicker du popover Filtres
 * Index Vehicles, plus arrays d'options et compteur de filtres actifs.
 *
 * Extrait de `pages/User/Vehicles/Index/Index.vue` dans le cadre du
 * plan-remediation Vague 1 Lot 4 D12 (F-34-008 · pages Index trop
 * grosses) pour respecter R7 ADR-0013 + mémoire
 * `feedback_vue_composables_extraction`.
 *
 * Le composable n'embarque AUCUN `ref()` propre · il agit uniquement
 * comme façade typée sur `tableState.filters`. Le test couvre le compteur
 * et les conversions String <-> Enum (le reste est trivialement testé via
 * les Feature `VehicleControllerTest` côté backend).
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
    /** Liste des années disponibles pour le sélecteur année financière. */
    availableYears: ComputedRef<readonly number[]>;
};

export type UseVehiclesIndexFiltersReturn = {
    // Filtres v-model
    searchModel: ComputedRef<string>;
    selectedYearModel: ComputedRef<number>;
    statusModel: ComputedRef<string | number>;
    energySourceModel: ComputedRef<string | number>;
    pollutantCategoryModel: ComputedRef<string | number>;
    handicapAccessModel: ComputedRef<boolean>;
    firstRegistrationYearMinModel: ComputedRef<number | null>;
    firstRegistrationYearMaxModel: ComputedRef<number | null>;
    includeExitedModel: ComputedRef<boolean>;

    // Options arrays statiques (computed pour cohérence avec yearOptions)
    statusOptions: SelectOption[];
    energySourceOptions: SelectOption[];
    pollutantCategoryOptions: SelectOption[];
    yearOptions: ComputedRef<YearOption[]>;

    // Compteur de filtres actifs (popover badge)
    activeFiltersCount: ComputedRef<number>;
};

export function useVehiclesIndexFilters(
    options: UseVehiclesIndexFiltersOptions,
): UseVehiclesIndexFiltersReturn {
    const { tableState, availableYears } = options;

    // ---------------------------------------------------------------
    // Options arrays statiques (Enum → label).
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // v-model wrappers (façade typée sur tableState.filters).
    // ---------------------------------------------------------------

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

    // ---------------------------------------------------------------
    // Compteur filtres actifs (badge popover).
    // ---------------------------------------------------------------

    const activeFiltersCount = computed<number>(() => {
        let n = 0;
        const f = tableState.filters.value;

        if (f.status !== null) {
            n += 1;
        }

        // includeExited défaut true · compté comme filtre actif uniquement
        // si l'utilisateur a explicitement décoché (override = exclure).
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
