<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import { formatEur } from '@/Utils/format/formatEur';

type VehicleRow = App.Data.User.Vehicle.VehicleListItemData;

const props = defineProps<{
    vehicles: VehicleRow[];
    fiscalYear: number;
}>();

const columns = computed<readonly DataTableColumn<VehicleRow>[]>(() => [
    { key: 'licensePlate', label: 'Immatriculation' },
    { key: 'model', label: 'Modèle' },
    { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
    { key: 'fullYearTax', label: `Coût plein ${props.fiscalYear}`, align: 'right' },
]);

const statusLabel: Record<string, string> = {
    active: 'Active',
    maintenance: 'Maintenance',
    sold: 'Vendu',
    destroyed: 'Détruit',
    other: 'Autre',
};

const statusDotClass: Record<string, string> = {
    active: 'bg-emerald-500',
    maintenance: 'bg-amber-500',
    sold: 'bg-slate-400',
    destroyed: 'bg-rose-500',
    other: 'bg-slate-400',
};

const handleRowClick = (row: VehicleRow): void => {
    router.visit(vehiclesShowRoute.url({ vehicle: row.id }));
};
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
