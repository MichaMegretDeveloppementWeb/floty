<script setup lang="ts">
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { useContractsTable } from '@/Composables/Contract/Index/useContractsTable';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type ContractRow = App.Data.User.Contract.ContractListItemData;

defineProps<{
    contracts: ContractRow[];
}>();

const { columns, typeLabel, handleRowClick } = useContractsTable();
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="contracts"
        :row-key="(row) => row.id"
        clickable
        @row-click="handleRowClick"
    >
        <template #cell-vehicleLicensePlate="{ row }">
            <Plate :value="row.vehicleLicensePlate" />
        </template>
        <template #cell-companyShortCode="{ row }">
            <span class="font-mono text-sm font-semibold text-slate-700">
                {{ row.companyShortCode }}
            </span>
        </template>
        <template #cell-startDate="{ value }">
            {{ formatDateFr(String(value)) }}
        </template>
        <template #cell-endDate="{ value }">
            {{ formatDateFr(String(value)) }}
        </template>
        <template #cell-durationDays="{ value }">
            {{ value }} j
        </template>
        <template #cell-contractType="{ row }">
            <span
                :class="[
                    'inline-flex rounded-md px-2 py-0.5 text-xs font-medium',
                    row.contractType === 'lcd'
                        ? 'bg-emerald-100 text-emerald-800'
                        : row.contractType === 'lld'
                          ? 'bg-indigo-100 text-indigo-800'
                          : 'bg-slate-100 text-slate-700',
                ]"
            >
                {{ typeLabel[row.contractType] }}
            </span>
        </template>
    </DataTable>
</template>
