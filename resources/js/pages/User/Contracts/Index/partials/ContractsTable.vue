<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { useContractsTable } from '@/Composables/Contract/Index/useContractsTable';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type ContractRow = App.Data.User.Contract.ContractListItemData;

defineProps<{
    contracts: ContractRow[];
}>();

const { columns, shortLabel, badgeTone, handleRowClick } = useContractsTable();
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
            <CompanyTag
                :name="row.companyLegalName"
                :initials="row.companyShortCode.slice(0, 2)"
                :color="row.companyColor"
            />
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
            <Badge :tone="badgeTone[row.contractType]">
                {{ shortLabel[row.contractType] }}
            </Badge>
        </template>
    </DataTable>
</template>
