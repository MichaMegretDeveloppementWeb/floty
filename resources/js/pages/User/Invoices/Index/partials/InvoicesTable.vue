<script setup lang="ts">
import { AlertTriangle, ChevronRight } from 'lucide-vue-next';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';
import { MONTH_LABELS } from '@/Utils/format/monthLabels';

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

</script>

<template>
    <DataTable
        class="hidden md:block"
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
            <div class="flex flex-col gap-0.5">
                <div class="flex items-center gap-2">
                    <span
                        :class="[
                            'font-mono text-sm whitespace-nowrap',
                            row.isObsolete ? 'text-slate-400 line-through' : '',
                        ]"
                    >{{ row.invoiceNumber }}</span>
                    <Tooltip v-if="row.hasDivergence && !row.isObsolete" max-width="20rem">
                        <span
                            class="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-sans font-medium text-amber-800 whitespace-nowrap"
                        >
                            <AlertTriangle class="shrink-0" :size="10" :stroke-width="2" />
                            À régénérer
                        </span>
                        <template #content>
                            Données obsolètes : le périmètre contractuel a changé depuis l'émission. Ouvrez l'annexe pour la régénérer.
                        </template>
                    </Tooltip>
                    <span
                        v-if="row.isObsolete"
                        class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-slate-50 px-1.5 py-0.5 text-[10px] font-sans font-medium text-slate-600 whitespace-nowrap"
                    >
                        Obsolète
                    </span>
                </div>
                <span
                    v-if="row.isObsolete && row.supersededByInvoiceNumber"
                    class="font-mono text-[11px] text-slate-500"
                >
                    Remplacée par {{ row.supersededByInvoiceNumber }}
                </span>
            </div>
        </template>

        <template #cell-companyShortCode="{ row }">
            <div class="flex flex-col">
                <span class="font-medium whitespace-nowrap text-slate-900">{{ row.companyShortCode }}</span>
                <span class="text-xs text-slate-500">{{ row.companyLegalName }}</span>
            </div>
        </template>

        <template #cell-period="{ row }">
            <span class="whitespace-nowrap">{{ MONTH_LABELS[row.month - 1] }} {{ row.year }}</span>
        </template>

        <template #cell-totalHtCents="{ row }">
            <span class="font-mono tabular-nums whitespace-nowrap">{{ formatEur(row.totalHtCents / 100, 2) }}</span>
        </template>

        <template #cell-generatedAt="{ row }">
            <span class="font-mono text-sm whitespace-nowrap text-slate-600">{{ formatDateFr(row.generatedAt) }}</span>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucune annexe ne correspond à votre recherche.
                </p>
                <p class="text-xs text-slate-500">
                    Essayez d'élargir les filtres pour voir plus de résultats.
                </p>
            </div>
        </template>
    </DataTable>

    <ul v-if="invoices.length > 0" class="flex flex-col gap-2 md:hidden">
        <li v-for="row in invoices" :key="row.id">
            <button
                type="button"
                class="flex w-full cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-left transition-colors duration-[120ms] ease-out hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-100"
                @click="emit('row-click', row)"
            >
                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm">{{ row.invoiceNumber }}</span>
                        <span
                            v-if="row.hasDivergence"
                            class="inline-flex items-center gap-1 rounded-md border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800"
                        >
                            <AlertTriangle :size="10" :stroke-width="2" />
                            À régénérer
                        </span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm font-medium text-slate-900">{{ row.companyShortCode }}</span>
                        <span class="text-xs text-slate-500">{{ row.companyLegalName }}</span>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ MONTH_LABELS[row.month - 1] }} {{ row.year }}
                        <span class="mx-1 text-slate-300">·</span>
                        <span class="font-mono tabular-nums text-slate-700">{{ formatEur(row.totalHtCents / 100, 2) }}</span>
                    </p>
                    <p class="font-mono text-[11px] text-slate-400">
                        Émise le {{ formatDateFr(row.generatedAt) }}
                    </p>
                </div>
                <ChevronRight
                    :size="16"
                    :stroke-width="1.75"
                    class="shrink-0 text-slate-400"
                    aria-hidden="true"
                />
            </button>
        </li>
    </ul>
    <div
        v-else
        class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-white py-8 text-center md:hidden"
    >
        <p class="text-sm font-medium text-slate-700">
            Aucune annexe ne correspond à votre recherche.
        </p>
        <p class="text-xs text-slate-500">
            Essayez d'élargir les filtres pour voir plus de résultats.
        </p>
    </div>
</template>
