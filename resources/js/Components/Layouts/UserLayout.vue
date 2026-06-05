<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { TriangleAlert } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import CommandPalette from '@/Components/Features/GlobalSearch/CommandPalette.vue';
import SidebarNav from '@/Components/Layouts/UserLayout/SidebarNav.vue';
import TopBar from '@/Components/Layouts/UserLayout/TopBar.vue';
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { useFlashToasts } from '@/Composables/Shared/useFlashToasts';

useFlashToasts();

const page = usePage();
const schedulerHeartbeatStale = computed<boolean>(() => page.props.schedulerHeartbeatStale === true);

withDefaults(
    defineProps<{
        activePath?: string;
    }>(),
    {},
);

const isMobile = useMediaQuery('(max-width: 767px)');
const sidebarOpen = ref<boolean>(false);

watch(isMobile, (mobile) => {
    if (!mobile) {
        sidebarOpen.value = false;
    }
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <SidebarNav v-model:open="sidebarOpen" :active-path="activePath" />

        <div class="flex min-h-screen min-w-0 flex-col md:pl-16 wide:pl-60">
            <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <main class="flex-1">
                <div class="mx-auto max-w-[1600px] px-4 py-6 md:px-8 md:py-8">
                    <div
                        v-if="schedulerHeartbeatStale"
                        class="mb-6 flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 max-w-[80em] mx-auto"
                        role="alert"
                    >
                        <TriangleAlert
                            :size="18"
                            :stroke-width="1.75"
                            class="mt-0.5 shrink-0 text-amber-600"
                            aria-hidden="true"
                        />
                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-semibold text-amber-800">Planificateur inactif</p>
                            <p class="text-sm text-amber-700">
                                Les rappels automatiques de contrôles ne sont peut-être plus envoyés.
                                Contactez l'administrateur.
                            </p>
                        </div>
                    </div>
                    <slot />
                </div>
            </main>
        </div>

        <ToastContainer />
        <CommandPalette />
    </div>
</template>
