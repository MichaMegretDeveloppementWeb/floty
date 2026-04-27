<script setup lang="ts">
import { computed } from 'vue';
import type { BadgeTone } from '@/types/ui';

const props = withDefaults(
    defineProps<{
        tone?: BadgeTone;
    }>(),
    {
        tone: 'slate',
    },
);

const toneClasses = computed<string>(() => {
    switch (props.tone) {
        case 'slate':
            return 'bg-slate-100 text-slate-600';
        case 'blue':
            return 'bg-blue-50 text-blue-700';
        case 'emerald':
            return 'bg-emerald-50 text-emerald-700';
        case 'amber':
            return 'bg-amber-50 text-amber-700';
        case 'rose':
            return 'bg-rose-50 text-rose-700';
        default: {
            const _exhaustive: never = props.tone;

            throw new Error(`Tone non géré : ${_exhaustive as string}`);
        }
    }
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-sm px-1.5 py-0.5 text-micro font-semibold tracking-wider uppercase',
            toneClasses,
        ]"
    >
        <slot />
    </span>
</template>
