/**
 * État de la table Contrats de la fiche Company Show (chantier N.1).
 *
 * Pattern server-side strict (cf. ADR-0020) : pagination + tri + filtre
 * période géré par `useServerTableState`, partial reload Inertia v3
 * sur les seules clés `contracts` + `contractsQuery`.
 *
 * **Périmètre filtres (V1)** : période uniquement. Pas de search, pas
 * de filtre type/véhicule/conducteur — la fiche Company est déjà
 * scope-restreinte à une entreprise, on garde dense et focal. Si le
 * besoin émerge, ajouter ici.
 *
 * **Pas de sélecteur d'année global** : le filtre période vit
 * localement dans cet onglet. Aucune dépendance au sélecteur
 * d'année (qui sera retiré par chantier J).
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as contractsShowRoute } from '@/routes/user/contracts';
import type { DataTableColumn } from '@/types/ui';
import {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';

type ContractRow = App.Data.User.Contract.ContractListItemData;

export type CompanyContractSortKey =
    | 'vehicle'
    | 'startDate'
    | 'endDate'
    | 'duration'
    | 'type';

const COLUMN_TO_SORT_KEY: Partial<Record<string, CompanyContractSortKey>> = {
    vehicleLicensePlate: 'vehicle',
    startDate: 'startDate',
    endDate: 'endDate',
    durationDays: 'duration',
    contractType: 'type',
};

export type CompanyContractFilters = {
    periodStart: string | null;
    periodEnd: string | null;
};

export function useCompanyContractsTable(opts: {
    query: App.Data.User.Contract.ContractIndexQueryData;
}): {
    columns: readonly DataTableColumn<ContractRow>[];
    state: ServerTableState<CompanyContractFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: ContractRow) => void;
    shortLabel: typeof contractTypeShortLabel;
    badgeTone: typeof contractTypeBadgeTone;
} {
    const columns: readonly DataTableColumn<ContractRow>[] = [
        { key: 'vehicleLicensePlate', label: 'Véhicule' },
        { key: 'startDate', label: 'Du', mono: true },
        { key: 'endDate', label: 'Au', mono: true },
        { key: 'durationDays', label: 'Durée', align: 'right', mono: true },
        { key: 'contractType', label: 'Type' },
    ];

    const state = useServerTableState<CompanyContractFilters>({
        only: ['contracts', 'contractsQuery', 'contractsStats'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            periodStart: null,
            periodEnd: null,
        },
        initialFilters: {
            periodStart: opts.query.periodStart,
            periodEnd: opts.query.periodEnd,
        },
        serializeFilters: (f) => ({
            periodStart: f.periodStart,
            periodEnd: f.periodEnd,
            // `tab=contracts` est forcé dans l'URL pour que tout reload
            // (filtre période, tri, pagination) préserve l'onglet actif —
            // sinon `useServerTableState` (qui fait `router.get(pathname,
            // cleanParams)`) écrase le `?tab=` injecté par `useCompanyTabs`
            // et un F5 utilisateur retombe sur Vue d'ensemble.
            tab: 'contracts',
        }),
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
    }

    function onRowClick(row: ContractRow): void {
        router.visit(contractsShowRoute.url({ contract: row.id }));
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        onHeaderClick,
        onRowClick,
        shortLabel: contractTypeShortLabel,
        badgeTone: contractTypeBadgeTone,
    };
}
