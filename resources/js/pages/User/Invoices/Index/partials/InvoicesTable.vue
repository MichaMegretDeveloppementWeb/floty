<script setup lang="ts">
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type InvoiceRow = App.Data.User.Invoice.InvoiceListItemData;

defineProps<{
    invoices: InvoiceRow[];
    columns: readonly DataTableColumn<InvoiceRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: InvoiceRow];
}>();

const MONTH_LABELS = [
    'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
] as const;
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="invoices"
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
                :label="col.label"
                :sort-key="col.key"
                :active-key="activeSortColumnKey"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : 'left'"
                @click="emit('header-click', col.key)"
            />
        </template>

        <template #cell-invoiceNumber="{ row }">
            <span class="font-mono text-sm">{{ row.invoiceNumber }}</span>
        </template>

        <template #cell-companyShortCode="{ row }">
            <div class="flex flex-col">
                <span class="font-medium text-slate-900">{{ row.companyShortCode }}</span>
                <span class="text-xs text-slate-500">{{ row.companyLegalName }}</span>
            </div>
        </template>

        <template #cell-period="{ row }">
            <span>{{ MONTH_LABELS[row.month - 1] }} {{ row.year }}</span>
        </template>

        <template #cell-totalHtCents="{ row }">
            <span class="font-mono tabular-nums">{{ formatEur(row.totalHtCents / 100, 2) }}</span>
        </template>

        <template #cell-generatedAt="{ row }">
            <span class="font-mono text-sm text-slate-600">{{ formatDateFr(row.generatedAt) }}</span>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucune facture ne correspond à votre recherche.
                </p>
                <p class="text-xs text-slate-500">
                    Essayez d'élargir les filtres pour voir plus de résultats.
                </p>
            </div>
        </template>
    </DataTable>
</template>
