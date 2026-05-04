<script setup lang="ts">
/**
 * Table « Répartition fiscale par véhicule » de l'onglet Fiscalité
 * de la fiche Company Show (chantier N.2).
 *
 * 1 ligne par véhicule utilisé par l'entreprise sur l'année
 * sélectionnée. Footer total avec sommes par colonne.
 *
 * Cohérent avec le composant miroir côté Vehicle Show
 * (`Vehicles/Show/partials/CompanyFiscalBreakdownTable.vue`) qui
 * affiche l'inverse (1 véhicule × N entreprises).
 */
import { router } from '@inertiajs/vue3';
import Card from '@/Components/Ui/Card/Card.vue';
import DataTable from '@/Components/Ui/DataTable/DataTable.vue';
import Plate from '@/Components/Ui/Plate/Plate.vue';
import { show as vehicleShowRoute } from '@/routes/user/vehicles';
import type { DataTableColumn } from '@/types/ui';
import { formatEur } from '@/Utils/format/formatEur';

type FiscalRow = App.Data.User.Company.CompanyVehicleFiscalRowData;

const props = defineProps<{
    fiscal: App.Data.User.Company.CompanyFiscalYearData;
}>();

const columns: readonly DataTableColumn<FiscalRow>[] = [
    { key: 'licensePlate', label: 'Véhicule' },
    { key: 'daysUsed', label: 'Jours', align: 'right', mono: true },
    { key: 'proratoPercent', label: 'Prorata', align: 'right', mono: true },
    { key: 'taxCo2', label: 'Taxe CO₂', align: 'right', mono: true },
    {
        key: 'taxPollutants',
        label: 'Taxe polluants',
        align: 'right',
        mono: true,
    },
    { key: 'taxTotal', label: 'Total', align: 'right', mono: true },
];

function onRowClick(row: FiscalRow): void {
    router.visit(vehicleShowRoute(row.vehicleId).url);
}
</script>

<template>
    <Card>
        <DataTable
            :columns="columns"
            :rows="props.fiscal.rows"
            :row-key="(row) => row.vehicleId"
            clickable
            @row-click="onRowClick"
        >
            <template #cell-licensePlate="{ row }">
                <div class="flex flex-col items-start gap-1">
                    <Plate :value="row.licensePlate" />
                    <span
                        v-if="row.brand !== null || row.model !== null"
                        class="text-xs text-slate-500"
                    >
                        {{
                            [row.brand, row.model]
                                .filter((v) => v !== null)
                                .join(' ')
                        }}
                    </span>
                </div>
            </template>
            <template #cell-daysUsed="{ value }">{{ value }} j</template>
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
                    Total {{ props.fiscal.year }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm font-semibold text-slate-900 tabular-nums"
                >
                    {{ props.fiscal.totalDays }} j
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-400 tabular-nums"
                >
                    —
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-700 tabular-nums"
                >
                    {{ formatEur(props.fiscal.totalTaxCo2) }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm text-slate-700 tabular-nums"
                >
                    {{ formatEur(props.fiscal.totalTaxPollutants) }}
                </td>
                <td
                    class="px-[18px] py-2.5 text-right font-mono text-sm font-semibold text-slate-900 tabular-nums"
                >
                    {{ formatEur(props.fiscal.totalTaxAll) }}
                </td>
            </template>
        </DataTable>
    </Card>
</template>
