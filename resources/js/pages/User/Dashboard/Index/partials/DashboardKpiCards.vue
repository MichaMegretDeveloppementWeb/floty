<script setup lang="ts">
import { computed } from 'vue';
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

type TrendDirection = 'up' | 'down' | 'flat';

const props = defineProps<{
    kpis: App.Data.User.Dashboard.DashboardKpiData;
}>();

const comparison = computed(() => props.kpis.previousYearComparison);

/**
 * Formate la date Y-1 « 5 mai 2025 » pour la ligne Δ. La date arrive en
 * ISO (YYYY-MM-DD) depuis le backend et représente le même jour-mois
 * Y-1 que aujourd'hui.
 */
function formatComparisonDate(iso: string): string {
    const [y, m, d] = iso.split('-');

    return new Date(Number(y), Number(m) - 1, Number(d)).toLocaleDateString(
        'fr-FR',
        { day: 'numeric', month: 'long', year: 'numeric' },
    );
}

/**
 * Construit la ligne Δ « +12 % vs 5 mai 2025 ». Renvoie `null` si la
 * comparaison n'est pas exploitable (delta null = base précédente
 * vide).
 */
function buildTrend(
    delta: number | null,
    suffix: string,
): { text: string; direction: TrendDirection } | null {
    if (delta === null || delta === undefined) {
        return null;
    }

    if (delta === 0) {
        return { text: `±0${suffix}`, direction: 'flat' };
    }

    const sign = delta > 0 ? '+' : '';
    const direction: TrendDirection = delta > 0 ? 'up' : 'down';

    return { text: `${sign}${delta}${suffix}`, direction };
}

const trendJours = computed(() =>
    comparison.value ? buildTrend(comparison.value.deltaJoursVehiculePercent, ' %') : null,
);
const trendContracts = computed(() =>
    comparison.value ? buildTrend(comparison.value.deltaContractsPercent, ' %') : null,
);
const trendTaxes = computed(() =>
    comparison.value ? buildTrend(comparison.value.deltaTaxesDuesPercent, ' %') : null,
);
const trendOccupation = computed(() =>
    comparison.value ? buildTrend(comparison.value.deltaTauxOccupationPoints, ' pt') : null,
);

const comparisonCaption = computed<string | null>(() => {
    if (comparison.value === null) {
        return null;
    }

    return `vs ${formatComparisonDate(comparison.value.endDate)}`;
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
        <KpiCard
            label="Jours-véhicule occupés"
            :value="kpis.joursVehicule.toLocaleString('fr-FR')"
            :trend="trendJours?.text"
            :trend-direction="trendJours?.direction"
        >
            <template #caption>
                Cumul du 1ᵉʳ janvier {{ kpis.year }} à aujourd'hui<span
                    v-if="comparisonCaption"
                >
                    · {{ comparisonCaption }}</span>
            </template>
        </KpiCard>

        <KpiCard
            label="Contrats"
            :value="kpis.contracts.toLocaleString('fr-FR')"
            :trend="trendContracts?.text"
            :trend-direction="trendContracts?.direction"
        >
            <template #caption>
                Total {{ kpis.year }} · dont {{ kpis.contractsActiveNow }} actif{{ kpis.contractsActiveNow > 1 ? 's' : '' }} aujourd'hui<span
                    v-if="comparisonCaption"
                >
                    · {{ comparisonCaption }}</span>
            </template>
        </KpiCard>

        <KpiCard
            label="Taxes dues"
            :value="formatEur(kpis.taxesDues)"
            :trend="trendTaxes?.text"
            :trend-direction="trendTaxes?.direction"
        >
            <template #caption>
                Estimation YTD (CO₂ + polluants)<span
                    v-if="comparisonCaption"
                >
                    · {{ comparisonCaption }}</span>
            </template>
        </KpiCard>

        <KpiCard
            label="Taux d'occupation flotte"
            :value="kpis.tauxOccupation.toLocaleString('fr-FR')"
            suffix="%"
            :trend="trendOccupation?.text"
            :trend-direction="trendOccupation?.direction"
        >
            <template #caption>
                Jours-véhicule réalisés / théoriques<span
                    v-if="comparisonCaption"
                >
                    · {{ comparisonCaption }}</span>
            </template>
        </KpiCard>
    </div>
</template>
