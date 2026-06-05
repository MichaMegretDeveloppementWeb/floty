<script setup lang="ts">
/**
 * Indicateur d'attribut compact : icône lucide seule + tooltip au survol (refonte
 * UI Chantier B). Remplace les badges texte verbeux (indisponibilité, réducteur
 * fiscal) par un signal visuel discret, qui évoque sans encombrer.
 */
import { computed } from 'vue';
import type { Component } from 'vue';
import Tooltip from '@/Components/Ui/Tooltip/Tooltip.vue';
import type { BadgeTone } from '@/types/ui';

const props = withDefaults(
    defineProps<{
        icon: Component;
        label: string;
        tone?: BadgeTone;
        size?: number;
    }>(),
    {
        tone: 'slate',
        size: 16,
    },
);

const toneClass = computed<string>(() => {
    switch (props.tone) {
        case 'slate':
            return 'text-slate-500';
        case 'blue':
            return 'text-blue-600';
        case 'emerald':
            return 'text-emerald-600';
        case 'amber':
            return 'text-amber-600';
        case 'rose':
            return 'text-rose-600';
        default: {
            const _exhaustive: never = props.tone;

            throw new Error(`Tone non géré : ${_exhaustive as string}`);
        }
    }
});
</script>

<template>
    <Tooltip>
        <span class="inline-flex" role="img" :aria-label="label">
            <component
                :is="icon"
                :size="size"
                :stroke-width="1.75"
                :class="toneClass"
                aria-hidden="true"
            />
        </span>
        <template #content>{{ label }}</template>
    </Tooltip>
</template>
