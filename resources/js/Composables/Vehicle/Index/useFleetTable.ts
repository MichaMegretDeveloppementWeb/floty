/**
 * Configuration de la table Index Vehicles (server-side, cf. ADR-0020).
 *
 * Particularités :
 *  - Filtres `includeExited` (boolean) + `status` (VehicleStatus | null)
 *  - Colonnes `fullYearTax` et `rentalPriceFullYear` affichées mais NON
 *    triables (valeurs calculées par l'aggregator fiscal et le module
 *    facturation V1.2 — règle ADR-0020 D6)
 *  - Sélecteur d'année **local à la page** (chantier η anticipé) :
 *    pilote uniquement les colonnes financières. Géré comme un filtre
 *    `year` dans `useServerTableState` pour bénéficier de la sérialisation
 *    URL + partial reload Inertia natifs (chaque changement d'année
 *    déclenche un recalcul backend des `fullYearTax`).
 *
 * Le rendu reste dans `FleetTable.vue` (slots cell-*).
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

// Mapping clé colonne UI → sortKey backend (whitelist VehicleIndexQueryData).
// Les colonnes `fullYearTax` et `rentalPriceFullYear` n'ont pas d'entrée
// car non triables (D6).
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
    /** Année des colonnes financières (Coût plein, Prix location). */
    year: number;
};

export function useFleetTable(opts: {
    query: App.Data.User.Vehicle.VehicleIndexQueryData;
    /** Année résolue par le backend (selectedYear), sert de fallback. */
    selectedYear: number;
    /** Année réelle (= année du resolver), URL omise si on y revient. */
    currentRealYear: number;
}): {
    columns: ComputedRef<readonly DataTableColumn<VehicleRow>[]>;
    state: ServerTableState<FleetFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: VehicleRow) => void;
} {
    const state = useServerTableState<FleetFilters>({
        only: ['vehicles', 'query', 'selectedYear'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            status: null,
            // Défaut true : on affiche les véhicules retirés par défaut
            // (consultation/édition rétroactive). Décocher pour les masquer.
            includeExited: true,
            energySource: null,
            pollutantCategory: null,
            handicapAccess: null,
            firstRegistrationYearMin: null,
            firstRegistrationYearMax: null,
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
            year: opts.selectedYear,
        },
        serializeFilters: (f) => ({
            status: f.status,
            // includeExited a comme défaut backend `true` : on ne l'envoie
            // dans l'URL que si l'utilisateur a décoché (override = 0).
            includeExited: f.includeExited ? null : 0,
            energySource: f.energySource,
            pollutantCategory: f.pollutantCategory,
            handicapAccess: f.handicapAccess === true ? 1 : null,
            firstRegistrationYearMin: f.firstRegistrationYearMin,
            firstRegistrationYearMax: f.firstRegistrationYearMax,
            // L'année réelle reste implicite (URL propre) ; on ne sérialise
            // que les valeurs « non triviales » à la R-C des autres filtres.
            year: f.year === opts.currentRealYear ? null : f.year,
        }),
    });

    // Labels dépendant de l'année courante du sélecteur — recalculés
    // automatiquement quand `state.filters.value.year` change.
    const columns = computed<readonly DataTableColumn<VehicleRow>[]>(() => {
        const year = state.filters.value.year;

        return [
            { key: 'licensePlate', label: 'Immatriculation' },
            { key: 'model', label: 'Modèle' },
            { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
            {
                key: 'fullYearTax',
                label: `Coût plein ${year}`,
                align: 'right',
            },
            {
                key: 'rentalPriceFullYear',
                label: `Prix location ${year}`,
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
        // fullYearTax n'a pas de mapping → no-op (header non interactif).
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
