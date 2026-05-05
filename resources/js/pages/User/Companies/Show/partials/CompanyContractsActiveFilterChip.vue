<script setup lang="ts">
/**
 * Chip dismissible affichant l'état du filtre période actif sur
 * l'onglet Contrats Company Show (chantier N.1.fixes).
 *
 * Smart label :
 * - Année pleine (YYYY-01-01 → YYYY-12-31)  → « Année 2024 »
 * - Période custom complète                → « 01/07/2024 → 31/12/2024 »
 * - Demi-bornée bas                        → « Depuis 01/07/2024 »
 * - Demi-bornée haut                       → « Jusqu'au 31/12/2024 »
 *
 * Affiche rien si `periodStart` ET `periodEnd` sont null.
 */
import { X } from 'lucide-vue-next';
import { computed } from 'vue';
import { formatDateFr } from '@/Utils/format/formatDateFr';

const props = defineProps<{
    periodStart: string | null;
    periodEnd: string | null;
}>();

const emit = defineEmits<{
    clear: [];
}>();

function isFullYear(start: string, end: string): number | null {
    const startMatch = /^(\d{4})-01-01$/.exec(start);
    const endMatch = /^(\d{4})-12-31$/.exec(end);

    if (startMatch === undefined || endMatch === undefined || startMatch === null || endMatch === null) {
        return null;
    }

    const startYear = startMatch[1];
    const endYear = endMatch[1];

    if (startYear === undefined || endYear === undefined || startYear !== endYear) {
        return null;
    }

    return Number.parseInt(startYear, 10);
}

const label = computed<string | null>(() => {
    const { periodStart, periodEnd } = props;

    if (periodStart === null && periodEnd === null) {
        return null;
    }

    if (periodStart !== null && periodEnd !== null) {
        const fullYear = isFullYear(periodStart, periodEnd);

        if (fullYear !== null) {
            return `Année ${fullYear}`;
        }

        return `${formatDateFr(periodStart)} → ${formatDateFr(periodEnd)}`;
    }

    if (periodStart !== null) {
        return `Depuis ${formatDateFr(periodStart)}`;
    }

    return `Jusqu'au ${formatDateFr(periodEnd as string)}`;
});
</script>

<template>
    <span
        v-if="label !== null"
        class="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 py-1 pr-1 pl-3 text-xs font-medium text-blue-800"
    >
        {{ label }}
        <button
            type="button"
            class="inline-flex h-5 w-5 cursor-pointer items-center justify-center rounded-full text-blue-700 transition-colors duration-[120ms] hover:bg-blue-100"
            aria-label="Retirer le filtre"
            @click="emit('clear')"
        >
            <X :size="12" :stroke-width="2" />
        </button>
    </span>
</template>
