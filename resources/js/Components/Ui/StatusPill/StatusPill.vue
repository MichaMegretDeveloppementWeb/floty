<script setup lang="ts">
import type { StatusTone } from '@/types/ui';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        tone?: StatusTone;
    }>(),
    {
        tone: 'slate',
    },
);

const toneClasses = computed<string>(() => {
    switch (props.tone) {
        case 'slate':
            return 'bg-slate-100 text-slate-600 border-slate-200';
        case 'blue':
            return 'bg-blue-50 text-blue-700 border-blue-200';
        case 'emerald':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'amber':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'rose':
            return 'bg-rose-50 text-rose-700 border-rose-200';
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
            'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium',
            toneClasses,
        ]"
    >
        <slot name="icon" />
        <slot />
    </span>
</template>
