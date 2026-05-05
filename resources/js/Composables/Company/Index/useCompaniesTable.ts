/**
 * Configuration de la table Index Companies (server-side, cf. ADR-0020).
 *
 * Particularités vs Drivers :
 *  - Filtre `isActive` (boolean tri-state : true / false / null)
 *  - Colonnes `daysUsed` et `annualTaxDue` affichées mais NON triables
 *    (valeurs calculées par l'aggregator fiscal — règle ADR-0020 D6)
 *
 * Le rendu reste dans `CompaniesTable.vue` (slots cell-*).
 */

import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { useServerTableState } from '@/Composables/Shared/useServerTableState';
import type { ServerTableState } from '@/Composables/Shared/useServerTableState';
import { show as companyShowRoute } from '@/routes/user/companies';
import type { DataTableColumn } from '@/types/ui';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

export type CompanySortKey = 'shortCode' | 'legalName' | 'siren' | 'city';

// Mapping clé colonne UI → sortKey backend (CompanyIndexQueryData whitelist).
// Les colonnes daysUsed et annualTaxDue n'ont PAS d'entrée car non triables.
const COLUMN_TO_SORT_KEY: Partial<Record<string, CompanySortKey>> = {
    company: 'legalName',
    siren: 'siren',
    city: 'city',
};

export type CompanyFilters = {
    isActive: boolean | null;
    contractsScope: 'with' | 'without' | null;
    companyType: 'corporate' | 'individual' | null;
    city: string | null;
    /** Année qui pilote les colonnes financières (chantier J). */
    year: number;
};

export function useCompaniesTable(opts: {
    query: App.Data.User.Company.CompanyIndexQueryData;
    selectedYear: number;
}): {
    columns: ComputedRef<readonly DataTableColumn<CompanyRow>[]>;
    state: ServerTableState<CompanyFilters>;
    activeSortColumnKey: ComputedRef<string | null>;
    onHeaderClick: (columnKey: string) => void;
    onRowClick: (row: CompanyRow) => void;
} {
    const state = useServerTableState<CompanyFilters>({
        only: ['companies', 'query', 'selectedYear'],
        initialPage: opts.query.page,
        initialPerPage: opts.query.perPage,
        initialSearch: opts.query.search ?? '',
        initialSortKey: opts.query.sortKey,
        initialSortDirection: opts.query.sortDirection,
        defaultFilters: {
            isActive: null,
            contractsScope: null,
            companyType: null,
            city: null,
            year: opts.selectedYear,
        },
        initialFilters: {
            isActive: opts.query.isActive,
            contractsScope: opts.query.contractsScope as
                | 'with'
                | 'without'
                | null,
            companyType: opts.query.companyType as
                | 'corporate'
                | 'individual'
                | null,
            city: opts.query.city,
            year: opts.query.year ?? opts.selectedYear,
        },
        serializeFilters: (f) => ({
            // Sérialisation booléenne : 1/0/null pour cohérence avec
            // Spatie Data ?isActive=1 / ?isActive=0 / absent.
            isActive: f.isActive === null ? null : f.isActive ? 1 : 0,
            contractsScope: f.contractsScope,
            companyType: f.companyType,
            city: f.city,
            year: f.year,
        }),
    });

    // Labels dépendant de l'année du sélecteur — recalculés automatiquement
    // quand `state.filters.value.year` change (chantier η Phase 3 fix). Sans
    // ça, les colonnes restaient figées sur l'année initiale.
    const columns = computed<readonly DataTableColumn<CompanyRow>[]>(() => {
        const year = state.filters.value.year;

        return [
            { key: 'company', label: 'Entreprise' },
            { key: 'siren', label: 'SIREN', mono: true },
            { key: 'city', label: 'Ville' },
            { key: 'daysUsed', label: `Jours ${year}`, mono: true },
            { key: 'annualTaxDue', label: `Taxe ${year}` },
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
        // Les colonnes sans entrée dans COLUMN_TO_SORT_KEY (daysUsed,
        // annualTaxDue) sont volontairement no-op au clic — pas de tri
        // possible côté serveur sur ces valeurs calculées.
    }

    function onRowClick(row: CompanyRow): void {
        router.visit(companyShowRoute(row.id).url);
    }

    return {
        columns,
        state,
        activeSortColumnKey,
        onHeaderClick,
        onRowClick,
    };
}
