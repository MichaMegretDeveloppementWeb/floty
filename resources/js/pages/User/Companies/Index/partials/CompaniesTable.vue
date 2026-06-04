<script setup lang="ts">
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatEur } from '@/Utils/format/formatEur';

type CompanyRow = App.Data.User.Company.CompanyListItemData;
type CompanyCosts = { annualTaxDue: number; rentalPriceTotal: number | null };

// Server-side sortable columns (mirror of CompanyIndexQueryData::allowedSortKeys()).
// `daysUsed` and `annualTaxDue` are intentionally excluded because they
// are non-SQL computed values (ADR-0020 D6).
const SORTABLE_COLUMNS: ReadonlySet<string> = new Set([
    'company',
    'siren',
    'city',
]);

const props = defineProps<{
    companies: CompanyRow[];
    columns: readonly DataTableColumn<CompanyRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
    // Deferred map keyed by companyId, undefined while the second-roundtrip
    // is still in flight (cells fall back to a skeleton).
    costs?: Record<number, CompanyCosts>;
}>();

const isCostsLoadedFor = (row: CompanyRow): boolean =>
    props.costs?.[row.id] !== undefined;

// Cost entry for a row in the loaded branch. Only call where
// `isCostsLoadedFor(row)` is guaranteed true (the `v-else` cells below):
// the entry is present, so the assertions are safe.
const costsFor = (row: CompanyRow): CompanyCosts => props.costs![row.id]!;

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: CompanyRow];
}>();
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="companies"
        :row-key="(row) => row.id"
        clickable
        @row-click="(row) => emit('row-click', row)"
    >
        <template
            v-for="column in columns"
            #[`header-${column.key}`]="{ column: col }"
            :key="column.key"
        >
            <SortableHeader
                v-if="SORTABLE_COLUMNS.has(col.key)"
                :label="col.label"
                :sort-key="col.key"
                :active-key="activeSortColumnKey"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : 'left'"
                @click="emit('header-click', col.key)"
            />
            <span
                v-else
                :class="[
                    'inline-flex w-full text-xs font-semibold tracking-wider text-slate-500 uppercase',
                    col.align === 'right' ? 'justify-end' : 'justify-start',
                ]"
            >
                {{ col.label }}
            </span>
        </template>

        <template #cell-company="{ row }">
            <CompanyTag
                :name="row.legalName"
                :initials="row.shortCode"
                :color="row.color"
            />
        </template>
        <template #cell-siren="{ value }">
            <span :class="['whitespace-nowrap', value ? '' : 'text-slate-300']">{{
                value ?? 'Non renseigné'
            }}</span>
        </template>
        <template #cell-city="{ value }">
            <span :class="value ? '' : 'text-slate-300'">{{
                value ?? 'Non renseignée'
            }}</span>
        </template>
        <template #cell-daysUsed="{ value }">
            <span class="whitespace-nowrap text-slate-700">{{ value }} j</span>
        </template>
        <template #cell-annualTaxDue="{ row }">
            <span
                v-if="!isCostsLoadedFor(row)"
                class="skeleton-shimmer inline-block h-3 w-14 rounded"
                aria-busy="true"
                aria-label="Calcul en cours"
            />
            <span
                v-else
                class="font-mono font-medium whitespace-nowrap text-slate-900"
            >
                {{ formatEur(costsFor(row).annualTaxDue) }}
            </span>
        </template>
        <template #cell-rentalPriceTotal="{ row }">
            <span
                v-if="!isCostsLoadedFor(row)"
                class="skeleton-shimmer inline-block h-3 w-16 rounded"
                aria-busy="true"
                aria-label="Calcul en cours"
            />
            <span
                v-else-if="costsFor(row).rentalPriceTotal !== null"
                class="font-mono whitespace-nowrap tabular-nums text-slate-900"
            >
                {{ formatEur(costsFor(row).rentalPriceTotal!) }}
            </span>
            <span
                v-else
                class="text-slate-300"
                title="Tarif annuel manquant pour au moins 1 véhicule"
            >
                ·
            </span>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucune entreprise ne correspond aux filtres
                </p>
                <p class="text-xs text-slate-500">
                    Modifiez votre recherche ou réinitialisez les filtres.
                </p>
            </div>
        </template>
    </DataTable>
</template>
