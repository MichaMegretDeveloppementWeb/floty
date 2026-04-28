<script setup lang="ts">
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { useFleetTable } from '@/Composables/Vehicle/Index/useFleetTable';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

const props = defineProps<{
    vehicles: VehicleRow[];
    fiscalYear: number;
}>();

const { columns, statusLabel, statusDotClass, handleRowClick } = useFleetTable(props);
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="vehicles"
        :row-key="(row) => row.id"
        clickable
        @row-click="handleRowClick"
    >
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
