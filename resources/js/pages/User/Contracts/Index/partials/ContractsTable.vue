<script setup lang="ts">
import Badge from '@/Components/Ui/Badge/Badge.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type {
    ContractSortKey,
} from '@/Composables/Contract/Index/useContractsTable';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';
import type {
    contractTypeBadgeTone,
    contractTypeShortLabel,
} from '@/Utils/labels/contractEnumLabels';


type ContractRow = App.Data.User.Contract.ContractListItemData;

defineProps<{
    contracts: ContractRow[];
    columns: readonly DataTableColumn<ContractRow>[];
    sortKey: ContractSortKey | null;
    sortDirection: 'asc' | 'desc';
    badgeTone: typeof contractTypeBadgeTone;
    shortLabel: typeof contractTypeShortLabel;
}>();

const emit = defineEmits<{
    sort: [key: ContractSortKey];
    'row-click': [row: ContractRow];
}>();

// Mapping colonne → clé de tri (toutes les colonnes sont triables ici).
const COLUMN_TO_SORT_KEY: Record<string, ContractSortKey> = {
    vehicleLicensePlate: 'vehicle',
    companyShortCode: 'company',
    startDate: 'startDate',
    endDate: 'endDate',
    durationDays: 'duration',
    contractType: 'type',
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
        :rows="contracts"
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
