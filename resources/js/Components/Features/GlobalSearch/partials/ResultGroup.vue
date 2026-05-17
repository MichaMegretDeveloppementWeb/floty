<script setup lang="ts">
defineProps<{
    title: string;
}>();
</script>

<template>
    <section class="flex flex-col gap-0.5">
        <header
            class="flex items-center justify-between gap-2 px-3 pt-2 pb-1"
        >
            <span
                class="text-xs leading-tight font-semibold tracking-wide text-slate-500 uppercase"
            >
                {{ title }}
            </span>
            <slot name="action" />
        </header>
        <slot />
    </section>
</template>
