<script setup lang="ts">
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

// Colonnes triables côté serveur (cf. VehicleIndexQueryData::allowedSortKeys()).
// fullYearTax est volontairement absent (valeur calculée non SQL,
// règle ADR-0020 D6).
const SORTABLE_COLUMNS: ReadonlySet<string> = new Set([
    'licensePlate',
    'model',
    'firstFrenchRegistrationDate',
]);

defineProps<{
    vehicles: VehicleRow[];
    columns: readonly DataTableColumn<VehicleRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: VehicleRow];
}>();
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="vehicles"
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

        <template #cell-licensePlate="{ row }">
            <div
                :class="[
                    'flex flex-wrap items-center gap-2',
                    row.isExited && 'opacity-60',
                ]"
            >
                <Plate :value="row.licensePlate" />
                <span
                    v-if="row.isExited"
                    class="rounded-md bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold tracking-wide text-slate-700 uppercase"
                >
                    Retiré
                </span>
            </div>
        </template>
        <template #cell-model="{ row }">
            <span :class="['text-slate-700', row.isExited && 'opacity-60']">
                <span class="font-semibold text-slate-900">
                    {{ row.brand }}
                </span>
                {{ row.model }}
            </span>
        </template>
        <template #cell-firstFrenchRegistrationDate="{ value }">
            {{ formatDateFr(String(value)) }}
        </template>
        <template #cell-fullYearTax="{ row }">
            <div class="flex flex-col items-end leading-tight">
                <span class="font-mono font-normal text-slate-900">
                    {{ formatEur(row.fullYearTax) }}
                </span>
                <span class="text-xs text-slate-400">
                    {{ formatEur(row.dailyTaxRate, 2) }} / jour
                </span>
            </div>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucun véhicule ne correspond aux filtres
                </p>
                <p class="text-xs text-slate-500">
                    Modifiez votre recherche ou réinitialisez les filtres.
                </p>
            </div>
        </template>
    </DataTable>
</template>
