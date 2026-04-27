<script setup lang="ts">
import { computed, useSlots } from 'vue';

type TrendDirection = 'up' | 'down' | 'flat';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string;
        suffix?: string;
        caption?: string;
        trend?: string;
        trendDirection?: TrendDirection;
    }>(),
    {
        trendDirection: 'up',
    },
);

const slots = useSlots();
const hasTrend = computed<boolean>(() => !!props.trend);

const trendClasses = computed<string>(() => {
    switch (props.trendDirection) {
        case 'up':
            return 'text-emerald-600';
        case 'down':
            return 'text-rose-600';
        case 'flat':
            return 'text-slate-500';
        default: {
            const _exhaustive: never = props.trendDirection;

            throw new Error(
                `Direction de tendance non gérée : ${_exhaustive as string}`,
            );
        }
    }
});
</script>

<template>
    <article
        class="flex flex-col gap-2.5 rounded-xl border border-slate-200 bg-white px-5 py-4 transition-shadow duration-[120ms] ease-out hover:shadow-sm"
    >
        <p
            class="text-xs font-semibold tracking-wider text-slate-500 uppercase text-balance"
        >
            {{ label }}
        </p>
        <div class="flex flex-wrap items-baseline gap-1.5">
            <p
                class="font-mono text-4xl font-semibold tracking-tight text-slate-900 tabular-nums leading-none"
            >
                {{ value }}
            </p>
            <p
                v-if="suffix"
                class="text-sm text-slate-400"
            >
                {{ suffix }}
            </p>
            <p
                v-if="hasTrend"
                :class="['ml-1 text-xs font-medium', trendClasses]"
            >
                {{ trend }}
            </p>
        </div>
        <p
            v-if="caption || slots.caption"
            class="text-sm text-slate-500 text-pretty"
        >
            <slot name="caption">{{ caption }}</slot>
        </p>
    </article>
</template>
