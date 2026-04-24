<script setup lang="ts">
defineProps<{
    id?: string;
    message?: string;
}>();
</script>

<template>
    <p
        v-if="message"
        :id="id"
        role="alert"
        class="mt-1 text-xs text-rose-600"
    >
        {{ message }}
    </p>
</template>
