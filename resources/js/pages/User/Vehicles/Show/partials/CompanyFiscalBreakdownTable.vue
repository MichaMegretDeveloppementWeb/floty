<script setup lang="ts">
import { Download } from 'lucide-vue-next';
import { computed } from 'vue';
import Button from '@/Components/Ui/Button/Button.vue';
import Card from '@/Components/Ui/Card/Card.vue';
import CompanyTag from '@/Components/Ui/CompanyTag/CompanyTag.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import type { DataTableColumn } from '@/types/ui';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

type Row = App.Data.User.Vehicle.VehicleCompanyUsageData;

const columns: readonly DataTableColumn<Row>[] = [
    { key: 'shortCode', label: 'Entreprise' },
    { key: 'daysUsed', label: 'Jours', align: 'right', mono: true },
    { key: 'proratoPercent', label: 'Prorata', align: 'right', mono: true },
    { key: 'taxCo2', label: 'Taxe CO₂', align: 'right', mono: true },
    { key: 'taxPollutants', label: 'Taxe polluant', align: 'right', mono: true },
    { key: 'taxTotal', label: 'Total', align: 'right', mono: true },
];

const totalDays = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.daysUsed, 0),
);

const totalProrato = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.proratoPercent, 0),
);

const totalCo2 = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.taxCo2, 0),
);

const totalPollutants = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.taxPollutants, 0),
);

const totalAll = computed<number>(() =>
    props.stats.companies.reduce((sum, c) => sum + c.taxTotal, 0),
);

const initialsOf = (shortCode: string): string =>
    shortCode.slice(0, 2).toUpperCase();
</script>

<template>
    <Card>
        <template #header>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-slate-900">
                    Répartition fiscale par entreprise utilisatrice
                </h2>
                <Button
                    variant="secondary"
                    size="sm"
                    disabled
                    title="Bientôt disponible"
                >
                    <template #icon-left>
                        <Download :size="14" :stroke-width="1.75" />
                    </template>
                    Export
                </Button>
            </div>
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
