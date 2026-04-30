<script setup lang="ts">
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { FleetSortKey } from '@/Composables/Vehicle/Index/useFleetTable';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

defineProps<{
    vehicles: VehicleRow[];
    columns: readonly DataTableColumn<VehicleRow>[];
    sortKey: FleetSortKey | null;
    sortDirection: 'asc' | 'desc';
    statusLabel: Record<string, string>;
    statusDotClass: Record<string, string>;
}>();

const emit = defineEmits<{
    sort: [key: FleetSortKey];
    'row-click': [row: VehicleRow];
}>();

const COLUMN_TO_SORT_KEY: Record<string, FleetSortKey> = {
    licensePlate: 'plate',
    model: 'model',
    firstFrenchRegistrationDate: 'firstReg',
    fullYearTax: 'fullYearTax',
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
                :label="col.label"
                :sort-key="COLUMN_TO_SORT_KEY[col.key] ?? ''"
                :active-key="sortKey"
                :direction="sortDirection"
                :align="col.align === 'right' ? 'right' : 'left'"
                @click="onHeaderClick(col.key)"
            />
        </template>

        <template #cell-licensePlate="{ row }">
            <div class="flex items-center gap-2">
                <span
                    :class="[
                        'inline-block h-2 w-2 shrink-0 rounded-full',
                        statusDotClass[row.currentStatus] ?? 'bg-slate-400',
                    ]"
                    :title="
                        statusLabel[row.currentStatus] ?? row.currentStatus
                    "
                    aria-hidden="true"
                />
                <Plate :value="row.licensePlate" />
            </div>
        </template>
        <template #cell-model="{ row }">
            <span class="text-slate-700">
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
    </DataTable>
</template>
