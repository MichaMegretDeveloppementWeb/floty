<script setup lang="ts">
defineProps<{
    title: string;
}>();
</script>

<template>
    <section class="flex flex-col gap-0.5">
        <header
            class="px-3 pt-2 pb-1 text-xs leading-tight font-semibold tracking-wide text-slate-500 uppercase"
        >
            {{ title }}
        </header>
        <slot />
    </section>
</template>
