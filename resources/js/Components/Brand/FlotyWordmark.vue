<script setup lang="ts">
withDefaults(
    defineProps<{
        height?: number;
    }>(),
    {
        height: 32,
    },
);
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 180 40"
        :height="height"
        fill="none"
        role="img"
        aria-label="Floty"
    >
        <g transform="translate(0,4)">
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
        </g>
        <text
            x="44"
            y="27"
            font-family="DM Sans, system-ui, sans-serif"
            font-size="22"
            font-weight="600"
            letter-spacing="-0.01em"
            fill="currentColor"
        >
            Floty
        </text>
    </svg>
</template>
