<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { ref, watch } from 'vue';
import CommandPalette from '@/Components/Features/GlobalSearch/CommandPalette.vue';
import SidebarNav from '@/Components/Layouts/UserLayout/SidebarNav.vue';
import TopBar from '@/Components/Layouts/UserLayout/TopBar.vue';
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { useFlashToasts } from '@/Composables/Shared/useFlashToasts';

useFlashToasts();

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

        <div class="flex min-h-screen min-w-0 flex-col md:pl-16 wide:pl-72">
            <TopBar @toggle-sidebar="sidebarOpen = !sidebarOpen" />
            <main class="flex-1">
                <div class="mx-auto max-w-[1600px] px-4 py-6 md:px-8 md:py-8">
                    <slot />
                </div>
            </main>
        </div>

        <ToastContainer />
        <CommandPalette />
    </div>
</template>
