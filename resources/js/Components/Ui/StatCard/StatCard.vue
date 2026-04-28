<script setup lang="ts">
import { computed, useSlots } from 'vue';

type Tone = 'slate' | 'blue' | 'emerald' | 'amber' | 'rose';

const props = withDefaults(
    defineProps<{
        value: string | number;
        label: string;
        caption?: string;
        tone?: Tone;
    }>(),
    {
        tone: 'slate',
    },
);

const slots = useSlots();

const valueClass = computed<string>(() => {
    switch (props.tone) {
        case 'slate':
            return 'text-slate-900';
        case 'blue':
            return 'text-blue-700';
        case 'emerald':
            return 'text-emerald-700';
        case 'amber':
            return 'text-amber-700';
        case 'rose':
            return 'text-rose-700';
        default: {
            const _exhaustive: never = props.tone;

            throw new Error(`Tone non géré : ${_exhaustive as string}`);
        }
    }
});

const iconWrapClass = computed<string>(() => {
    switch (props.tone) {
        case 'slate':
            return 'bg-slate-100 text-slate-500';
        case 'blue':
            return 'bg-blue-50 text-blue-600';
        case 'emerald':
            return 'bg-emerald-50 text-emerald-600';
        case 'amber':
            return 'bg-amber-50 text-amber-600';
        case 'rose':
            return 'bg-rose-50 text-rose-600';
        default:
            return 'bg-slate-100 text-slate-500';
    }
});
</script>

<template>
    <div
        class="flex flex-col gap-1 rounded-xl border border-slate-200 bg-white p-4"
    >
        <div class="flex items-start justify-between gap-3">
            <p
                class="text-xs font-medium tracking-wider text-slate-500 uppercase"
            >
                {{ label }}
            </p>
            <span
                v-if="slots.icon"
                :class="[
                    'inline-flex h-8 w-8 items-center justify-center rounded-lg',
                    iconWrapClass,
                ]"
            >
                <slot name="icon" />
            </span>
        </div>
        <p
            :class="[
                'text-2xl font-semibold tracking-tight',
                valueClass,
            ]"
        >
            {{ value }}
        </p>
        <p v-if="caption" class="text-xs text-slate-500">
            {{ caption }}
        </p>
        <div v-if="slots.action" class="mt-2">
            <slot name="action" />
        </div>
    </div>
</template>
