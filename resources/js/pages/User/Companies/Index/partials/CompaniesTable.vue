<script setup lang="ts">
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type {
    CompanySortKey,
} from '@/Composables/Company/Index/useCompaniesTable';
import type { DataTableColumn } from '@/types/ui';
import { formatEur } from '@/Utils/format/formatEur';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

defineProps<{
    companies: CompanyRow[];
    columns: readonly DataTableColumn<CompanyRow>[];
    sortKey: CompanySortKey | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    sort: [key: CompanySortKey];
}>();

const COLUMN_TO_SORT_KEY: Record<string, CompanySortKey> = {
    company: 'company',
    siren: 'siren',
    city: 'city',
    daysUsed: 'days',
    annualTaxDue: 'tax',
};

function onHeaderClick(columnKey: string): void {
    const sortKey = COLUMN_TO_SORT_KEY[columnKey];

    if (sortKey !== undefined) {
        emit('sort', sortKey);
    }
}
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="companies"
        :row-key="(row) => row.id"
    >
        <template
            v-for="column in columns"
            #[`header-${column.key}`]="{ column: col }"
            :key="column.key"
        >
            <SortableHeader
                :label="col.label"
                :sort-key="COLUMN_TO_SORT_KEY[col.key] ?? ''"
                :active-key="sortKey"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : 'left'"
                @click="onHeaderClick(col.key)"
            />
        </template>

        <template #cell-company="{ row }">
            <CompanyTag
                :name="row.legalName"
                :initials="row.shortCode"
                :color="row.color"
            />
        </template>
        <template #cell-siren="{ value }">
            {{ value ?? '—' }}
        </template>
        <template #cell-city="{ value }">
            {{ value ?? '—' }}
        </template>
        <template #cell-daysUsed="{ value }">
            <span class="text-slate-700">{{ value }} j</span>
        </template>
        <template #cell-annualTaxDue="{ value }">
            <span class="font-mono font-medium text-slate-900">
                {{ formatEur(Number(value)) }}
            </span>
        </template>
    </DataTable>
</template>
