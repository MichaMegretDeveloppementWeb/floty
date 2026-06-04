<script setup lang="ts">
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import SortableHeader from '@/Components/Ui/Table/SortableHeader.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatDateFr } from '@/Utils/format/formatDateFr';

type RentalDiscountRow = App.Data.User.RentalDiscount.RentalDiscountListItemData;

defineProps<{
    rentalDiscounts: RentalDiscountRow[];
    columns: readonly DataTableColumn<RentalDiscountRow>[];
    activeSortColumnKey: string | null;
    sortDirection: 'asc' | 'desc';
}>();

const emit = defineEmits<{
    'header-click': [columnKey: string];
    'row-click': [row: RentalDiscountRow];
}>();

const statusToTone = {
    active: 'emerald',
    planned: 'amber',
    expired: 'slate',
} as const;

const statusLabelMap = {
    active: 'Active',
    planned: 'Planifiée',
    expired: 'Expirée',
} as const;
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="rentalDiscounts"
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

        <template #cell-company="{ row }">
            <CompanyTag
                :name="row.companyLegalName"
                :initials="row.companyShortCode"
                :color="row.companyColor"
            />
        </template>

        <template #cell-period="{ row }">
            <div class="flex flex-col">
                <span class="text-sm text-slate-800">
                    Du {{ formatDateFr(row.startDate) }}
                </span>
                <span class="text-xs text-slate-500">
                    au {{ formatDateFr(row.endDate) }}
                </span>
            </div>
        </template>

        <template #cell-discount="{ row }">
            <RentalDiscountPill
                :basis-points="row.discountBasisPoints"
                :tone="statusToTone[row.status as keyof typeof statusToTone]"
                size="md"
            />
            <span v-if="row.label" class="ml-2 text-xs text-slate-500">
                « {{ row.label }} »
            </span>
        </template>

        <template #cell-vehicles="{ row }">
            <span
                v-if="row.isAllVehicles"
                class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-0.5 text-xs font-medium text-slate-700"
            >
                Tous les véhicules
            </span>
            <span
                v-else
                class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700"
            >
                {{ row.vehiclesCount }} véhicule{{ row.vehiclesCount > 1 ? 's' : '' }}
            </span>
        </template>

        <template #cell-status="{ row }">
            <span
                class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em]"
                :class="{
                    'text-emerald-700': row.status === 'active',
                    'text-amber-700': row.status === 'planned',
                    'text-slate-500': row.status === 'expired',
                }"
            >
                <span
                    class="inline-block size-1.5 rounded-full"
                    :class="{
                        'bg-emerald-500': row.status === 'active',
                        'bg-amber-500': row.status === 'planned',
                        'bg-slate-400': row.status === 'expired',
                    }"
                    aria-hidden="true"
                />
                {{ statusLabelMap[row.status as keyof typeof statusLabelMap] }}
            </span>
        </template>

        <template #empty>
            <div class="flex flex-col items-center gap-2 py-8 text-center">
                <p class="text-sm font-medium text-slate-700">
                    Aucune réduction commerciale ne correspond à votre recherche
                </p>
                <p class="text-xs text-slate-500">
                    Essayez avec un autre filtre ou videz les critères.
                </p>
            </div>
        </template>
    </DataTable>
</template>
