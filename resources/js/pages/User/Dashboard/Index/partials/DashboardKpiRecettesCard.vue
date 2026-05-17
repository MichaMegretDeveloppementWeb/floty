<script setup lang="ts">
import { computed } from 'vue';
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

type TrendDirection = 'up' | 'down' | 'flat';

const props = defineProps<{
    recettes: App.Data.User.Dashboard.DashboardKpiRecettesData;
}>();

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

const trendRecettes = computed(() =>
    buildTrend(props.recettes.deltaRecettesLocativesPercent, ' %'),
);

const comparisonCaption = computed<string | null>(() => {
    if (props.recettes.previousYearRecettesLocativesCents === null
        || props.recettes.previousYear === null
    ) {
        return null;
    }

    return `vs ${formatEur(props.recettes.previousYearRecettesLocativesCents / 100)} en ${props.recettes.previousYear}`;
});
</script>

<template>
    <KpiCard
        label="Recettes locatives"
        :value="formatEur(recettes.recettesLocativesCents / 100)"
        :trend="trendRecettes?.text"
        :trend-direction="trendRecettes?.direction"
    >
        <template #caption>
            Total HT {{ recettes.year }} · réalisé + prévu<span v-if="comparisonCaption">
                · {{ comparisonCaption }}</span>
        </template>
    </KpiCard>
</template>
