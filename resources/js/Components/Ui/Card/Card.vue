<script setup lang="ts">
import { useSlots } from 'vue';

withDefaults(
    defineProps<{
        padding?: 'sm' | 'md' | 'lg';
        interactive?: boolean;
    }>(),
    {
        padding: 'md',
        interactive: false,
    },
);

const slots = useSlots();
</script>

<template>
    <div
        :class="[
            'rounded-xl border border-slate-200 bg-white',
            interactive &&
                'transition-shadow duration-[120ms] ease-out hover:shadow-sm',
        ]"
    >
        <header
            v-if="slots.header"
            class="border-b border-slate-200 px-5 py-4"
        >
            <slot name="header" />
        </header>
        <div
            :class="[
                padding === 'sm' && 'p-4',
                padding === 'md' && 'px-5 py-4',
                padding === 'lg' && 'p-6',
            ]"
        >
            <slot />
        </div>
        <footer
            v-if="slots.footer"
            class="border-t border-slate-200 bg-slate-50/60 px-5 py-3"
        >
            <slot name="footer" />
        </footer>
    </div>
</template>
