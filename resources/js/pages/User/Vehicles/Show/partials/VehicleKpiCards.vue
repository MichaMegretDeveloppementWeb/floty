<script setup lang="ts">
import { Calendar, Coins, Receipt } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

const actualTaxCaption = computed<string>(() => {
    if (props.stats.daysUsedThisYear === 0 || props.stats.daysInYear === 0) {
        return "Pas encore d'utilisation";
    }

    const percent = Math.round(
        (props.stats.daysUsedThisYear / props.stats.daysInYear) * 100,
    );

    return `${percent}% d'utilisation`;
});
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <StatCard
            tone="emerald"
            :value="formatEur(props.stats.actualTaxThisYear)"
            :label="`Taxe ${props.stats.fiscalYear} réelle`"
            :caption="actualTaxCaption"
        >
            <template #icon>
                <Receipt :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="`${props.stats.daysUsedThisYear} j`"
            :label="`Jours d'utilisation ${props.stats.fiscalYear}`"
            :caption="`sur ${props.stats.daysInYear} jours`"
        >
            <template #icon>
                <Calendar :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="formatEur(props.stats.fullYearTax)"
            :label="`Coût plein ${props.stats.fiscalYear}`"
            :caption="`${formatEur(props.stats.dailyTaxRate)} / jour`"
        >
            <template #icon>
                <Coins :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>
    </div>
</template>
