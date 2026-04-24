<script setup lang="ts">
withDefaults(
    defineProps<{
        size?: number;
    }>(),
    {
        size: 32,
    },
);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        :width="size"
        :height="size"
        fill="none"
        role="img"
        aria-label="Floty"
    >
        <circle
            cx="16"
            cy="16"
            r="5.5"
            stroke="currentColor"
            stroke-width="1.75"
        />
        <circle cx="16" cy="4.5" r="2.25" fill="currentColor" />
        <circle cx="26" cy="21.5" r="2.25" fill="currentColor" />
        <circle cx="6" cy="21.5" r="2.25" fill="var(--color-blue-500)" />
    </svg>
</template>
