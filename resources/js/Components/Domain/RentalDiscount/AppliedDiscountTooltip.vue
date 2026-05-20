<script setup lang="ts">
/**
 * Tooltip wrapping a RentalDiscountPill, revealing label, percentage and
 * savings on hover. Used on invoice Show and on CompanyBillingTab.
 */
import { computed } from 'vue';
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import { formatEur } from '@/Utils/format/formatEur';
import { formatPercentFromBasisPoints } from '@/Utils/format/formatPercent';

const props = withDefaults(
    defineProps<{
        basisPoints: number;
        /** Snapshot label of the discount (nullable when unset). */
        label?: string | null;
        /** Savings amount in cents (optional; revealed in the tooltip when provided). */
        discountCents?: number | null;
        /** Trigger pill size. */
        size?: 'sm' | 'md';
    }>(),
    {
        label: null,
        discountCents: null,
        size: 'sm',
    },
);

const percentLabel = computed<string>(() => formatPercentFromBasisPoints(props.basisPoints));

const savingsLabel = computed<string | null>(() => {
    if (props.discountCents === null || props.discountCents === 0) {
        return null;
    }

    return formatEur(props.discountCents / 100, 2);
});
</script>

<template>
    <Tooltip max-width="20rem">
        <RentalDiscountPill
            :basis-points="basisPoints"
            :size="size"
        />
        <template #content>
            <div class="flex flex-col gap-1">
                <span class="font-medium text-slate-100">
                    Réduction {{ percentLabel }} appliquée
                </span>
                <span v-if="label" class="text-slate-300">
                    « {{ label }} »
                </span>
                <span v-if="savingsLabel" class="text-emerald-300">
                    Réduction appliquée : -{{ savingsLabel }}
                </span>
            </div>
        </template>
    </Tooltip>
</template>
