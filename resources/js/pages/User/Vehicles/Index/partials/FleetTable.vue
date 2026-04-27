<script setup lang="ts">
import { computed } from 'vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
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
    { key: 'brand', label: 'Marque' },
    { key: 'model', label: 'Modèle' },
    { key: 'firstFrenchRegistrationDate', label: '1ʳᵉ immat.', mono: true },
    { key: 'annualTaxDue', label: `Taxe ${props.fiscalYear}` },
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
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="vehicles"
        :row-key="(row) => row.id"
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
        <template #cell-firstFrenchRegistrationDate="{ value }">
            {{ formatDateFr(String(value)) }}
        </template>
        <template #cell-annualTaxDue="{ value }">
            <span class="font-mono font-medium text-slate-900">
                {{ formatEur(Number(value)) }}
            </span>
        </template>
    </DataTable>
</template>
