<script setup lang="ts">
/**
 * Row of 4 "Present" KPI cards for the current calendar year. When the
 * fiscal engine has no rules for that year (`kpiFiscalAvailable=false`),
 * the tax-related cards show a neutral `·` and an explanatory caption.
 */
import { Calendar, Coins, Receipt, Wallet } from 'lucide-vue-next';
import { computed } from 'vue';
import StatCard from '@/Components/Ui/StatCard/StatCard.vue';
import { formatEur } from '@/Utils/format/formatEur';

const props = defineProps<{
    kpiStats: App.Data.User.Vehicle.VehicleYearStatsData;
    kpiYear: number;
    kpiFiscalAvailable: boolean;
}>();

const fiscalCaption = computed<string>(() =>
    props.kpiFiscalAvailable
        ? `année ${props.kpiYear}`
        : `Règles fiscales ${props.kpiYear} non implémentées`,
);

const actualTaxValue = computed<string>(() =>
    props.kpiFiscalAvailable ? formatEur(props.kpiStats.actualTax) : '·',
);

const fullYearTaxValue = computed<string>(() =>
    props.kpiFiscalAvailable ? formatEur(props.kpiStats.fullYearTax) : '·',
);

const rentalPriceValue = computed<string>(() =>
    props.kpiStats.rentalPrice !== null ? formatEur(props.kpiStats.rentalPrice) : '·',
);

const rentalPriceCaption = computed<string>(() =>
    props.kpiStats.rentalPrice !== null
        ? `année ${props.kpiYear}`
        : 'Tarif annuel non défini',
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <StatCard
            tone="slate"
            :value="`${props.kpiStats.daysUsed} j`"
            label="Jours d'utilisation"
            :caption="`année ${props.kpiYear}`"
        >
            <template #icon>
                <Calendar :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="emerald"
            :value="actualTaxValue"
            label="Taxe réelle"
            :caption="fiscalCaption"
        >
            <template #icon>
                <Receipt :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="fullYearTaxValue"
            label="Taxe pleine"
            :caption="fiscalCaption"
        >
            <template #icon>
                <Coins :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>

        <StatCard
            tone="slate"
            :value="rentalPriceValue"
            label="Montant loyer"
            :caption="rentalPriceCaption"
        >
            <template #icon>
                <Wallet :size="18" :stroke-width="1.75" />
            </template>
        </StatCard>
    </div>
</template>
