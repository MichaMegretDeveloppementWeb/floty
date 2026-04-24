<script setup lang="ts">
import SidebarNav from '@/Components/Layouts/UserLayout/SidebarNav.vue';
import TopBar from '@/Components/Layouts/UserLayout/TopBar.vue';
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { ref } from 'vue';

withDefaults(
    defineProps<{
        activePath?: string;
    }>(),
    {},
);

const year = defineModel<number>('year', { default: 2026 });
const internalYear = ref<number>(2026);
</script>

<template>
    <div class="flex min-h-screen bg-slate-50">
        <SidebarNav :active-path="activePath" />
        <div class="flex min-w-0 flex-1 flex-col">
            <TopBar v-model:year="internalYear" />
            <main class="flex-1 overflow-y-auto">
                <div class="mx-auto max-w-[1400px] px-8 py-8">
                    <slot :year="internalYear" />
                </div>
            </main>
        </div>
        <ToastContainer />
    </div>
</template>
