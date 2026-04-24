<script setup lang="ts">
import SidebarNav from '@/Components/Layouts/UserLayout/SidebarNav.vue';
import TopBar from '@/Components/Layouts/UserLayout/TopBar.vue';
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { useMediaQuery } from '@vueuse/core';
import { ref, watch } from 'vue';

withDefaults(
    defineProps<{
        activePath?: string;
    }>(),
    {},
);

const internalYear = ref<number>(2026);

const isMobile = useMediaQuery('(max-width: 767px)');
const sidebarOpen = ref<boolean>(false);

watch(isMobile, (mobile) => {
    if (!mobile) sidebarOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 wide:flex">
        <SidebarNav
            v-model:open="sidebarOpen"
            :active-path="activePath"
        />

        <div
            class="flex min-h-screen min-w-0 flex-1 flex-col md:pl-16 wide:pl-0"
        >
            <TopBar
                v-model:year="internalYear"
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
            />
            <main class="flex-1">
                <div class="mx-auto max-w-[1400px] px-4 py-6 md:px-8 md:py-8">
                    <slot :year="internalYear" />
                </div>
            </main>
        </div>

        <ToastContainer />
    </div>
</template>
