<script setup lang="ts">
import Kbd from '@/Components/Ui/Kbd/Kbd.vue';
import { Search } from 'lucide-vue-next';
import { computed, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        shortcut?: readonly string[];
        disabled?: boolean;
        ariaLabel?: string;
        id?: string;
    }>(),
    {
        disabled: false,
        shortcut: () => [],
    },
);

const modelValue = defineModel<string>({ required: true });

const autoId = useId();
const inputId = computed<string>(() => props.id ?? autoId);

const hasShortcut = computed<boolean>(() => props.shortcut.length > 0);
</script>

<template>
    <div class="relative">
        <Search
            :size="14"
            :stroke-width="1.75"
            class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-slate-400"
            aria-hidden="true"
        />
        <input
            :id="inputId"
            v-model="modelValue"
            type="search"
            :placeholder="placeholder"
            :disabled="disabled"
            :aria-label="ariaLabel"
            :class="[
                'w-full rounded-lg border border-slate-200 bg-white py-2 pl-9 text-base leading-tight text-slate-900 transition-colors duration-[120ms] ease-out placeholder:text-slate-400 focus-visible:border-slate-400 focus-visible:shadow-[0_0_0_3px_var(--color-slate-100)] focus-visible:outline-none disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-400',
                hasShortcut ? 'pr-24' : 'pr-3',
            ]"
        />
        <div
            v-if="hasShortcut"
            class="pointer-events-none absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1"
            aria-hidden="true"
        >
            <Kbd v-for="key in shortcut" :key="key">{{ key }}</Kbd>
        </div>
    </div>
</template>
