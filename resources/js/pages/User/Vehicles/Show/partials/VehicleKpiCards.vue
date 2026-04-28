<script setup lang="ts">
import { Calendar, Coins, Receipt } from 'lucide-vue-next';
import Button from '@/Components/Ui/Button/Button.vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { useVehicleKpiCards } from '@/Composables/Vehicle/Show/useVehicleKpiCards';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    stats: App.Data.User.Vehicle.VehicleUsageStatsData;
}>();

defineEmits<{
    'open-full-year-detail': [];
}>();

const { actualTaxCaption } = useVehicleKpiCards(props);
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
            :caption="`${formatEur(props.stats.dailyTaxRate, 2)} / jour`"
        >
            <template #icon>
                <Coins :size="18" :stroke-width="1.75" />
            </template>
            <template #action>
                <Button
                    variant="ghost"
                    size="sm"
                    class="xl:hidden"
                    @click="$emit('open-full-year-detail')"
                >
                    Voir le détail
                </Button>
            </template>
        </StatCard>
    </div>
</template>
