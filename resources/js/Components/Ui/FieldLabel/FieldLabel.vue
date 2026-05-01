<script setup lang="ts">
defineProps<{
    for: string;
    required?: boolean;
}>();
</script>

<template>
    <label :for="$props.for" class="text-md font-medium text-slate-600">
        <slot />
        <span v-if="required" aria-hidden="true" class="ml-0.5 text-rose-600">
            *
        </span>
    </label>
</template>
