<script setup lang="ts">
defineProps<{
    value: string;
}>();
</script>

<template>
    <span
        class="font-mono font-medium tracking-[0.02em] text-slate-900 uppercase"
    >
        {{ value }}
    </span>
</template>
