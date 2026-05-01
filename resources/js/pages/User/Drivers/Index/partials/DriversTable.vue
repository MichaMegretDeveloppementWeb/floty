<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import DriverBadge from '@/Components/Domain/Driver/DriverBadge.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import { show as showRoute } from '@/routes/user/drivers';
import type { DataTableColumn } from '@/types/ui';

type DriverRow = App.Data.User.Driver.DriverListItemData;

defineProps<{
    drivers: DriverRow[];
}>();

const columns: readonly DataTableColumn<DriverRow>[] = [
    { key: 'driver', label: 'Conducteur' },
    { key: 'companies', label: 'Entreprises' },
    { key: 'contractsCount', label: 'Contrats', mono: true },
    { key: 'actions', label: '', mono: false },
];
</script>

<template>
    <DataTable :columns="columns" :rows="drivers" :row-key="(row) => row.id">
        <template #cell-driver="{ row }">
            <Link
                :href="showRoute(row.id).url"
                class="text-blue-700 hover:underline"
            >
                <DriverBadge
                    :full-name="row.fullName"
                    :initials="row.initials"
                />
            </Link>
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

        <template #cell-actions="{ row }">
            <Link
                :href="showRoute(row.id).url"
                class="text-sm text-blue-600 hover:underline"
            >
                Voir
            </Link>
        </template>
    </DataTable>
</template>
