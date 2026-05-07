<script setup lang="ts">
import { onMounted, ref } from 'vue';
import Toast from '@/Components/Ui/Toast/Toast.vue';
import { useToasts } from '@/Composables/Shared/useToasts';

const { toasts, dismiss } = useToasts();

// Le `<Teleport to="body">` cause un Hydration mismatch en SSR
// (le serveur rend un placeholder `<script>` que le client remplace
// par un `<div>`). On gate le Teleport derrière un flag `mounted` pour
// ne le faire qu'au démarrage côté client, après l'hydration.
const mounted = ref<boolean>(false);

onMounted(() => {
    mounted.value = true;
});
</script>

<template>
    <Teleport
        v-if="mounted"
        to="body"
    >
        <div
            class="pointer-events-none fixed top-4 left-1/2 z-[60] flex -translate-x-1/2 flex-col items-center gap-3"
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
                    :duration="toast.duration"
                    @dismiss="dismiss(toast.id)"
                />
            </div>
        </div>
    </Teleport>
</template>
