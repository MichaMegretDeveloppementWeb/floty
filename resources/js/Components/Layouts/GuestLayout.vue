<script setup lang="ts">
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { useFlashToasts } from '@/Composables/Shared/useFlashToasts';

/**
 * Guest layout for unauthenticated pages (Login, ForgotPassword, ResetPassword).
 * Bridges session flash messages to UI toasts and delegates layout to the slot.
 */
useFlashToasts();
</script>

<template>
    <slot />
    <ToastContainer />
</template>
