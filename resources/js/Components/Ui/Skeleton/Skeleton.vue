<script setup lang="ts">
/**
 * Skeleton placeholder with a left-to-right shimmer animation. Uses the
 * global `.skeleton-shimmer` class and keyframe defined in
 * `resources/css/app.css`. No default border-radius: pass one via class.
 */
import { computed } from 'vue';

const props = defineProps<{
    class?: string;
}>();

const rootClass = computed<string>(() => ['skeleton-shimmer', props.class].filter(Boolean).join(' '));
</script>

<template>
    <div :class="rootClass" aria-busy="true" aria-live="polite" />
</template>
