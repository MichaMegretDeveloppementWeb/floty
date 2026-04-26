<script setup lang="ts">
import Toast from '@/Components/Ui/Toast/Toast.vue';
import { useToasts } from '@/Composables/Shared/useToasts';

const { toasts, dismiss } = useToasts();
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed top-4 right-4 z-[60] flex flex-col gap-3"
            aria-live="polite"
            aria-atomic="false"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto"
            >
                <Toast
                    :tone="toast.tone"
                    :title="toast.title"
                    :description="toast.description"
                    @dismiss="dismiss(toast.id)"
                />
            </div>
        </div>
    </Teleport>
</template>
