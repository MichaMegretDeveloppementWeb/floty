<script setup lang="ts">
/**
 * Tooltip d'information sur la réduction commerciale appliquée à une
 * ligne facture (Lot 3 chantier RentalDiscount). Encapsule
 * `RentalDiscountPill` (le trigger visuel) dans un `Tooltip` qui
 * révèle au hover · libellé + pourcentage + détail montant éventuel.
 *
 * Utilisé sur la page Show facture (badge ligne) et sur le tableau
 * mensuel CompanyBillingTab (badge mois).
 */
import { computed } from 'vue';
import RentalDiscountPill from '@/Components/Domain/RentalDiscount/RentalDiscountPill.vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import { formatEur } from '@/Utils/format/formatEur';
import { formatPercentFromBasisPoints } from '@/Utils/format/formatPercent';

const props = withDefaults(
    defineProps<{
        basisPoints: number;
        /** Libellé snapshot de la réduction (peut être null si non renseigné). */
        label?: string | null;
        /** Montant économisé en cents (optionnel, affiché dans le tooltip si fourni). */
        discountCents?: number | null;
        /** Taille du pill trigger. */
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
                    Économie : {{ savingsLabel }}
                </span>
            </div>
        </template>
    </Tooltip>
</template>
