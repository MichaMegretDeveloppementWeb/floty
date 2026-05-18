<script setup lang="ts">
/**
 * Badge présentationnel pour un pourcentage de réduction commerciale
 * (Lot 3 chantier RentalDiscount). Affiche `X,XX %` avec un picto
 * `BadgePercent` discret, dans une teinte emerald (cohérent avec la
 * sémantique "économie / gain").
 *
 * Composant slim · pas de logique d'état, pas d'IO. Utilisable partout :
 *   - Inline dans une ligne facture
 *   - Trigger d'un `AppliedDiscountTooltip`
 *   - Listing de la section "Réductions" sur Company Show
 */
import { BadgePercent } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatPercentFromBasisPoints } from '@/Utils/format/formatPercent';

type Size = 'sm' | 'md';
type Tone = 'emerald' | 'slate' | 'amber';

const props = withDefaults(
    defineProps<{
        /** Pourcentage stocké en basis points (1 050 bp = 10,50 %). */
        basisPoints: number;
        /** Densité visuelle · `sm` pour inline ligne / `md` pour bandeau. */
        size?: Size;
        /**
         * Teinte selon le statut de la réduction · `emerald` = active
         * (défaut), `amber` = planifiée, `slate` = expirée / snapshot
         * historique sur une facture obsolète.
         */
        tone?: Tone;
        /** Affiche l'icône (défaut `true`). Mettre `false` pour gain place. */
        withIcon?: boolean;
    }>(),
    {
        size: 'sm',
        tone: 'emerald',
        withIcon: true,
    },
);

const label = computed<string>(() => formatPercentFromBasisPoints(props.basisPoints));

const containerClasses = computed<string>(() => {
    const base = 'inline-flex items-center gap-1 rounded-full border font-medium tabular-nums whitespace-nowrap';
    const sizeClasses = props.size === 'sm' ? 'px-1.5 py-0.5 text-[11px]' : 'px-2.5 py-0.5 text-xs';
    const toneClasses = {
        emerald: 'border-emerald-200 bg-emerald-50 text-emerald-700',
        slate: 'border-slate-200 bg-slate-50 text-slate-600',
        amber: 'border-amber-200 bg-amber-50 text-amber-700',
    }[props.tone];

    return `${base} ${sizeClasses} ${toneClasses}`;
});

const iconSize = computed<number>(() => (props.size === 'sm' ? 11 : 13));
</script>

<template>
    <span :class="containerClasses">
        <BadgePercent
            v-if="withIcon"
            :size="iconSize"
            :stroke-width="2"
            aria-hidden="true"
        />
        {{ label }}
    </span>
</template>
