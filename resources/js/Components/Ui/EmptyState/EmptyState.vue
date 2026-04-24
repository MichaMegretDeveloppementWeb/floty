<script setup lang="ts">
import { useSlots } from 'vue';

defineProps<{
    title: string;
    description?: string;
}>();

const slots = useSlots();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center"
    >
        <div
            v-if="slots.icon"
            class="flex size-10 items-center justify-center rounded-xl bg-slate-100 text-slate-500"
            aria-hidden="true"
        >
            <slot name="icon" />
        </div>
        <h3 class="text-xl font-semibold text-slate-900">{{ title }}</h3>
        <p
            v-if="description"
            class="max-w-md text-sm text-slate-500 text-pretty"
        >
            {{ description }}
        </p>
        <div v-if="slots.actions" class="mt-3 flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
