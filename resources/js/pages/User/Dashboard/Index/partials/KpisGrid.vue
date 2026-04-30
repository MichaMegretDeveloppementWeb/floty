<script setup lang="ts">
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

defineProps<{
    stats: App.Data.User.Dashboard.DashboardStatsData;
    fiscalYear: number;
}>();
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <KpiCard
            label="Véhicules en flotte"
            :value="String(stats.vehiclesCount)"
            caption="Véhicules non sortis"
        />
        <KpiCard
            label="Entreprises actives"
            :value="String(stats.companiesCount)"
            caption="Clients utilisateurs de la flotte"
        />
        <KpiCard
            :label="`Jours-véhicule ${fiscalYear}`"
            :value="stats.contractDaysYear.toLocaleString('fr-FR')"
            caption="Total cumulé sur l'année sélectionnée"
        />
        <KpiCard
            :label="`Taxes dues ${fiscalYear}`"
            :value="formatEur(stats.totalTaxDue)"
            caption="CO₂ + polluants, toutes entreprises"
        />
    </div>
</template>
