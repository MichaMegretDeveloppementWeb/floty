<script setup lang="ts">
defineProps<{
    active: boolean;
}>();
</script>

<template>
    <div class="relative">
        <slot />
        <span
            v-if="active"
            class="pointer-events-none absolute right-1.5 top-1.5 h-2 w-2 rounded-full bg-amber-500 ring-2 ring-white"
            aria-label="À compléter manuellement"
            role="img"
        />
    </div>
</template>
