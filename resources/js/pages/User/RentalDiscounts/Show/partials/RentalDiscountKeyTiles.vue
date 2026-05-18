<script setup lang="ts">
import { computed } from 'vue';
import { useRentalDiscountPeriodMetrics } from '@/Composables/RentalDiscount/Show/useRentalDiscountPeriodMetrics';
import { formatPercentFromBasisPoints } from '@/Utils/format/formatPercent';

const props = defineProps<{
    rentalDiscount: App.Data.User.RentalDiscount.RentalDiscountData;
}>();

const discountLabel = computed<string>(() =>
    formatPercentFromBasisPoints(props.rentalDiscount.discountBasisPoints, 2),
);

const periodMetrics = useRentalDiscountPeriodMetrics(
    () => props.rentalDiscount.startDate,
    () => props.rentalDiscount.endDate,
    () => props.rentalDiscount.status as 'planned' | 'active' | 'expired',
);
</script>

<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <article class="rounded-xl border border-slate-200 bg-white px-6 py-5">
            <p class="eyebrow mb-2.5">
                Taux de remise
            </p>
            <p class="font-mono text-[30px] leading-none font-medium tracking-tight text-slate-900 tabular-nums">
                {{ discountLabel }}
            </p>
            <p class="mt-3 text-xs leading-relaxed text-slate-500">
                Stocké à <span class="font-medium text-slate-700">{{ rentalDiscount.discountBasisPoints }} bp</span>
                · pas de 0,5 %
                <br>Appliqué au prorata des jours d'utilisation effective
            </p>
        </article>

        <article class="rounded-xl border border-slate-200 bg-white px-6 py-5">
            <p class="eyebrow mb-2.5">
                {{ periodMetrics.label }}
            </p>
            <p class="font-mono text-[30px] leading-none font-medium tracking-tight text-slate-900 tabular-nums">
                {{ periodMetrics.bigValue }}
            </p>
            <p class="mt-3 text-xs leading-relaxed text-slate-500">
                {{ periodMetrics.meta }}
            </p>
            <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100">
                <div
                    class="h-full rounded-full bg-slate-700 transition-[width] duration-300 ease-out"
                    :style="{ width: `${periodMetrics.progressPercent}%` }"
                />
            </div>
            <div class="mt-2 flex justify-between font-mono text-[11px] text-slate-500 tabular-nums">
                <span>{{ periodMetrics.timelineLeft }}</span>
                <span v-if="periodMetrics.timelineCenter" class="text-slate-700">
                    {{ periodMetrics.timelineCenter }}
                </span>
                <span>{{ periodMetrics.timelineRight }}</span>
            </div>
        </article>
    </div>
</template>
