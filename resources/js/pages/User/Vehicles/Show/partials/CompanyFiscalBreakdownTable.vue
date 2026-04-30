<script setup lang="ts">
import Card from '@/Components/Ui/Card/Card.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import { useCompanyFiscalBreakdownTable } from '@/Composables/Vehicle/Show/useCompanyFiscalBreakdownTable';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

const {
    columns,
    totalDays,
    totalProrato,
    totalCo2,
    totalPollutants,
    totalAll,
    initialsOf,
} = useCompanyFiscalBreakdownTable(props);
</script>

<template>
    <Card>
        <template #header>
            <h2 class="text-base font-semibold text-slate-900">
                Répartition fiscale par entreprise utilisatrice
            </h2>
        </template>

        <p
            v-if="props.stats.companies.length === 0"
            class="text-sm text-slate-500 italic"
        >
            Aucune entreprise utilisatrice cette année.
        </p>

        <DataTable
            v-else
            :columns="columns"
            :rows="props.stats.companies"
            :row-key="(row) => row.companyId"
        >
            <template #cell-shortCode="{ row }">
                <CompanyTag
                    :name="row.legalName"
                    :initials="initialsOf(row.shortCode)"
                    :color="row.color"
                />
            </template>
            <template #cell-proratoPercent="{ value }">
                <span class="text-slate-500">
                    {{ Number(value).toFixed(1) }}%
                </span>
            </template>
            <template #cell-taxCo2="{ value }">
                {{ formatEur(Number(value)) }}
            </template>
            <template #cell-taxPollutants="{ value }">
                {{ formatEur(Number(value)) }}
            </template>
            <template #cell-taxTotal="{ value }">
                <span class="font-semibold text-slate-900">
                    {{ formatEur(Number(value)) }}
                </span>
            </template>

            <template #footer-row>
                <td
                    class="px-[18px] py-2.5 text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Total {{ props.stats.fiscalYear }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm font-semibold text-slate-900 tabular-nums"
                >
                    {{ totalDays }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-500 tabular-nums"
                >
                    {{ totalProrato.toFixed(1) }}%
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-700 tabular-nums"
                >
                    {{ formatEur(totalCo2) }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-700 tabular-nums"
                >
                    {{ formatEur(totalPollutants) }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm font-semibold text-slate-900 tabular-nums"
                >
                    {{ formatEur(totalAll) }}
                </td>
            </template>
        </DataTable>
    </Card>
</template>
