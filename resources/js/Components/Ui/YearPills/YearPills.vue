<script setup lang="ts">
/**
 * Clickable year pills for quick fiscal-year switching on entity tabs.
 * Most recent year leftmost. Scrollable horizontally for entities with
 * many years of history.
 */
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        /** Continuous range [firstYear..currentYear]. */
        years: readonly number[];
        /** Active year, or null when no year filter applies. */
        activeYear: number | null;
        /** Disables interaction during an Inertia partial reload. */
        loading?: boolean;
        /** Optional leading label, defaults to "Année". */
        label?: string;
        /**
         * Years with at least one pending action in the current context.
         * Renders an amber dot on top of the pill to guide the user.
         */
        yearsWithTodo?: readonly number[];
    }>(),
    { loading: false, label: 'Année', yearsWithTodo: () => [] },
);

const emit = defineEmits<{
    select: [year: number];
}>();

const reversedYears = computed<readonly number[]>(() =>
    [...props.years].reverse(),
);

const yearsWithTodoSet = computed<Set<number>>(() => new Set(props.yearsWithTodo));

function pillClass(year: number): string {
    const active = props.activeYear === year;
    const disabled = props.loading;

    const base = 'inline-flex items-center gap-1.5 snap-start shrink-0 rounded-full border px-3 py-1 text-sm transition-colors duration-[120ms]';
    const cursor = disabled ? 'cursor-wait opacity-60' : 'cursor-pointer';

    if (active) {
        return `${base} ${cursor} border-blue-300 bg-blue-50 font-semibold text-blue-700`;
    }

    return `${base} ${cursor} border-slate-200 bg-white font-medium text-slate-700 ${disabled ? '' : 'hover:border-slate-300 hover:bg-slate-50'}`;
}
</script>

<template>
    <div class="flex items-center gap-3">
        <span
            class="shrink-0 text-xs font-medium tracking-wide text-slate-500 uppercase"
        >
            {{ label }}
        </span>
        <div
            class="flex flex-1 snap-x snap-mandatory gap-1.5 overflow-x-auto scrollbar-hide"
        >
            <button
                v-for="year in reversedYears"
                :key="year"
                type="button"
                :disabled="loading"
                :class="pillClass(year)"
                @click="emit('select', year)"
            >
                <span>{{ year }}</span>
                <span
                    v-if="yearsWithTodoSet.has(year)"
                    class="inline-block size-1.5 shrink-0 rounded-full bg-amber-400"
                    title="Action en attente sur cet exercice"
                    aria-hidden="true"
                />
            </button>
        </div>
    </div>
</template>

<style scoped>
.scrollbar-hide {
    scrollbar-width: none;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
</style>
