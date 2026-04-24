<script setup lang="ts" generic="T extends string">
type Tab = {
    value: T;
    label: string;
    count?: number;
};

defineProps<{
    tabs: Tab[];
    ariaLabel?: string;
}>();

const active = defineModel<T>({ required: true });
</script>

<template>
    <div
        role="tablist"
        :aria-label="ariaLabel"
        class="inline-flex gap-0.5 rounded-lg bg-slate-100 p-0.5"
    >
        <button
            v-for="tab in tabs"
            :key="tab.value"
            type="button"
            role="tab"
            :aria-selected="active === tab.value"
            :class="[
                'flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-medium transition-colors duration-[120ms] ease-out',
                active === tab.value
                    ? 'bg-white text-slate-900 shadow-sm'
                    : 'text-slate-600 hover:text-slate-900',
            ]"
            @click="active = tab.value"
        >
            <span>{{ tab.label }}</span>
            <span
                v-if="typeof tab.count === 'number'"
                :class="[
                    'rounded-full px-1.5 font-mono text-[10px]',
                    active === tab.value
                        ? 'bg-slate-100 text-slate-700'
                        : 'bg-slate-200/60 text-slate-500',
                ]"
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
