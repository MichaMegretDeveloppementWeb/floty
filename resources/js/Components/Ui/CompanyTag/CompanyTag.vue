<script setup lang="ts">
import { computed } from 'vue';

type CompanyColor = App.Enums.Company.CompanyColor;

const props = withDefaults(
    defineProps<{
        name: string;
        initials: string;
        color?: CompanyColor;
        /**
         * Circle-only variant (no pill, no inline name). Full name remains
         * exposed via aria-label + title for accessibility and hover. Use
         * in dense contexts (narrow table cells, compact cards).
         */
        compact?: boolean;
    }>(),
    {
        color: 'indigo',
        compact: false,
    },
);

const dotClasses = computed<string>(() => {
    switch (props.color) {
        case 'indigo':
            return 'bg-company-indigo';
        case 'emerald':
            return 'bg-company-emerald';
        case 'amber':
            return 'bg-company-amber';
        case 'rose':
            return 'bg-company-rose';
        case 'violet':
            return 'bg-company-violet';
        case 'teal':
            return 'bg-company-teal';
        case 'orange':
            return 'bg-company-orange';
        case 'cyan':
            return 'bg-company-cyan';
        default: {
            const _exhaustive: never = props.color;

            throw new Error(`Couleur non gérée : ${_exhaustive as string}`);
        }
    }
});
</script>

<template>
    <span
        v-if="compact"
        :class="[
            'inline-flex size-[25px] items-center justify-center rounded-full text-[10px] font-semibold tracking-tight text-white uppercase',
            dotClasses,
        ]"
        :aria-label="name"
        :title="name"
    >
        {{ initials }}
    </span>
    <span
        v-else
        class="inline-flex w-fit max-w-full items-center gap-1.5 rounded-full border border-slate-200 bg-white py-0.5 pr-2.5 pl-0.5 text-xs text-slate-700"
    >
        <span
            :class="[
                'flex size-[25px] items-center justify-center rounded-full text-[10px] font-semibold tracking-tight text-white uppercase',
                dotClasses,
            ]"
            aria-hidden="true"
        >
            {{ initials }}
        </span>
        <span>{{ name }}</span>
    </span>
</template>
