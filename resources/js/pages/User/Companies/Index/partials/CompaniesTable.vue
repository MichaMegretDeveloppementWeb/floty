<script setup lang="ts">
import { computed } from 'vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatEur } from '@/Utils/format/formatEur';

type CompanyRow = App.Data.User.Company.CompanyListItemData;

const props = defineProps<{
    companies: CompanyRow[];
    fiscalYear: number;
}>();

const columns = computed<readonly DataTableColumn<CompanyRow>[]>(() => [
    { key: 'company', label: 'Entreprise' },
    { key: 'siren', label: 'SIREN', mono: true },
    { key: 'city', label: 'Ville' },
    { key: 'daysUsed', label: `Jours ${props.fiscalYear}`, mono: true },
    { key: 'annualTaxDue', label: `Taxe ${props.fiscalYear}` },
]);
</script>

<template>
    <DataTable
        :columns="columns"
        :rows="companies"
        :row-key="(row) => row.id"
    >
        <template #cell-company="{ row }">
            <div class="flex items-center gap-2">
                <span
                    :class="[
                        'inline-block h-2 w-2 shrink-0 rounded-full',
                        row.isActive ? 'bg-emerald-500' : 'bg-slate-400',
                    ]"
                    :title="row.isActive ? 'Active' : 'Inactive'"
                    aria-hidden="true"
                />
                <CompanyTag
                    :name="row.legalName"
                    :initials="row.shortCode"
                    :color="row.color"
                />
            </div>
        </template>
        <template #cell-siren="{ value }">
            {{ value ?? '—' }}
        </template>
        <template #cell-city="{ value }">
            {{ value ?? '—' }}
        </template>
        <template #cell-daysUsed="{ value }">
            <span class="text-slate-700">{{ value }} j</span>
        </template>
        <template #cell-annualTaxDue="{ value }">
            <span class="font-mono font-medium text-slate-900">
                {{ formatEur(Number(value)) }}
            </span>
        </template>
    </DataTable>
</template>
