<script setup lang="ts">
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        min?: number;
        max?: number;
    }>(),
    {
        min: 2022,
        max: 2030,
    },
);

const year = defineModel<number>({ required: true });

const canPrev = computed<boolean>(() => year.value > props.min);
const canNext = computed<boolean>(() => year.value < props.max);

const prev = (): void => {
    if (canPrev.value) {
        year.value = year.value - 1;
    }
};

const next = (): void => {
    if (canNext.value) {
        year.value = year.value + 1;
    }
};
</script>

<template>
    <div
        class="inline-flex items-center overflow-hidden rounded-lg border border-slate-200 bg-white"
        role="group"
        aria-label="Sélecteur d'année fiscale"
    >
        <button
            type="button"
            aria-label="Année précédente"
            :disabled="!canPrev"
            class="flex size-8 items-center justify-center text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-slate-100 focus-visible:outline-none disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"
            @click="prev"
        >
            <ChevronLeft :size="14" :stroke-width="1.75" />
        </button>
        <p
            class="min-w-[64px] border-x border-slate-200 px-3 text-center font-mono text-base font-medium text-slate-900 tabular-nums"
        >
            {{ year }}
        </p>
        <button
            type="button"
            aria-label="Année suivante"
            :disabled="!canNext"
            class="flex size-8 items-center justify-center text-slate-500 transition-colors duration-[120ms] ease-out hover:bg-slate-50 hover:text-slate-900 focus-visible:ring-2 focus-visible:ring-slate-100 focus-visible:outline-none disabled:cursor-not-allowed disabled:text-slate-300 disabled:hover:bg-transparent"
            @click="next"
        >
            <ChevronRight :size="14" :stroke-width="1.75" />
        </button>
    </div>
</template>
