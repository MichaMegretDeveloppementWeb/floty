<script setup lang="ts">
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';

type DriverRow = App.Data.User.Driver.DriverListItemData;

defineProps<{
    drivers: DriverRow[];
    columns: readonly DataTableColumn<DriverRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: DriverRow];
}>();
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="drivers"
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

        <template #cell-driver="{ row }">
            <DriverBadge :full-name="row.fullName" :initials="row.initials" />
        </template>

        <template #cell-companies="{ row }">
            <div class="flex flex-wrap items-center gap-1.5">
                <CompanyTag
                    v-for="tag in row.activeCompanies"
                    :key="tag.companyId"
                    :name="tag.shortCode"
                    :initials="tag.shortCode"
                    :color="tag.color"
                />
                <span
                    v-if="
                        row.totalActiveCompaniesCount >
                        row.activeCompanies.length
                    "
                    class="rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600"
                >
                    +{{
                        row.totalActiveCompaniesCount -
                        row.activeCompanies.length
                    }}
                </span>
            </div>
        </template>

        <template #cell-contractsCount="{ row }">
            {{ row.contractsCount }}
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucun conducteur ne correspond à votre recherche
                </p>
                <p class="text-xs text-slate-500">
                    Essayez avec un autre nom ou videz le champ de recherche.
                </p>
            </div>
        </template>
    </DataTable>
</template>
