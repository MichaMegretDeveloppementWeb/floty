<script setup lang="ts">
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatEur } from '@/Utils/format/formatEur';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

// Colonnes triables côté serveur (cf. CompanyIndexQueryData::allowedSortKeys()).
// daysUsed et annualTaxDue sont volontairement absents (valeurs calculées
// non triables en SQL — règle ADR-0020 D6).
const SORTABLE_COLUMNS: ReadonlySet<string> = new Set([
    'company',
    'siren',
    'city',
]);

defineProps<{
    companies: CompanyRow[];
    columns: readonly DataTableColumn<CompanyRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

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
            <span :class="value ? '' : 'text-slate-300'">{{
                value ?? 'Non renseigné'
            }}</span>
        </template>
        <template #cell-city="{ value }">
            <span :class="value ? '' : 'text-slate-300'">{{
                value ?? 'Non renseignée'
            }}</span>
        </template>
        <template #cell-daysUsed="{ value }">
            <span class="text-slate-700">{{ value }} j</span>
        </template>
        <template #cell-annualTaxDue="{ value }">
            <span class="font-mono font-medium text-slate-900">
                {{ formatEur(Number(value)) }}
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
