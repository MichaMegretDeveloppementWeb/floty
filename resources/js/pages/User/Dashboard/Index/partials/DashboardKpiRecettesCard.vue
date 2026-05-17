<script setup lang="ts">
import KpiCard from '@/Components/Ui/KpiCard/KpiCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    recettes: App.Data.User.Dashboard.DashboardKpiRecettesData;
}>();
void props;
</script>

<template>
    <KpiCard
        label="Recettes locatives"
        :value="formatEur(recettes.recettesLocativesCents / 100)"
    >
        <template #caption>
            Total HT {{ recettes.year }} · réalisé + prévu
        </template>
    </KpiCard>
</template>
