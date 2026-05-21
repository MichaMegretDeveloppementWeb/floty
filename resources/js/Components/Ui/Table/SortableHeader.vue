<script setup lang="ts">
import { ChevronDown, ChevronsUpDown, ChevronUp } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        label: string;
        sortKey: string;
        activeKey: string | null;
        direction: 'asc' | 'desc';
        align?: 'left' | 'right' | 'center';
        /**
         * When true, the label stays in the neutral slate tone whatever
         * the active state. Only the chevrons reflect the sort
         * direction. Used in single-key contexts (e.g. Planning
         * heatmap) where the active state is permanent and tinting the
         * label adds no information.
         */
        mutedLabel?: boolean;
    }>(),
    { mutedLabel: false },
);

defineEmits<{
    click: [];
}>();
</script>

<template>
    <button
        type="button"
        :class="[
            '-mx-[18px] -my-2.5 inline-flex w-full cursor-pointer items-center gap-1.5 px-[18px] py-2.5 text-xs font-semibold tracking-wider uppercase transition-colors duration-[120ms] ease-out hover:bg-slate-100',
            align === 'right' ? 'justify-end' : align === 'center' ? 'justify-center' : 'justify-start',
            mutedLabel || activeKey !== sortKey ? 'text-slate-500' : 'text-blue-700',
        ]"
        @click="$emit('click')"
    >
        <span>{{ label }}</span>
        <ChevronUp
            v-if="activeKey === sortKey && direction === 'asc'"
            :size="12"
            :stroke-width="2"
            :class="mutedLabel ? 'text-slate-500' : 'text-blue-600'"
        />
        <ChevronDown
            v-else-if="activeKey === sortKey && direction === 'desc'"
            :size="12"
            :stroke-width="2"
            :class="mutedLabel ? 'text-slate-500' : 'text-blue-600'"
        />
        <ChevronsUpDown
            v-else
            :size="12"
            :stroke-width="2"
            class="text-slate-300"
        />
    </button>
</template>
