<script setup lang="ts">
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    kpis: App.Data.User.Dashboard.DashboardKpiData;
}>();
void props;
</script>

<template>
    <KpiCard
        label="Jours-véhicule occupés"
        :value="kpis.joursVehicule.toLocaleString('fr-FR')"
    >
        <template #aside>
            <p class="font-mono text-base font-semibold text-slate-700 tabular-nums leading-none">
                {{ kpis.tauxOccupation.toLocaleString('fr-FR') }} %
            </p>
            <p class="mt-1 text-[0.625rem] font-medium tracking-wider uppercase text-slate-400">
                Occupation
            </p>
        </template>
        <template #caption>
            Cumul du 1ᵉʳ janvier {{ kpis.year }} à aujourd'hui
        </template>
    </KpiCard>

    <KpiCard
        label="Locations"
        :value="kpis.contracts.toLocaleString('fr-FR')"
    >
        <template #caption>
            Total {{ kpis.year }} · dont {{ kpis.contractsActiveNow }} actif{{ kpis.contractsActiveNow > 1 ? 's' : '' }} aujourd'hui
        </template>
    </KpiCard>

    <KpiCard
        label="Taxes dues"
        :value="formatEur(kpis.taxesDues)"
    >
        <template #caption>
            Estimation YTD (CO₂ + polluants)
        </template>
    </KpiCard>
</template>
