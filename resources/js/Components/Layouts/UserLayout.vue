<script setup lang="ts">
import SidebarNav from '@/Components/Layouts/UserLayout/SidebarNav.vue';
import TopBar from '@/Components/Layouts/UserLayout/TopBar.vue';
import ToastContainer from '@/Components/Ui/ToastContainer/ToastContainer.vue';
import { usePage } from '@inertiajs/vue3';
import { useMediaQuery } from '@vueuse/core';
import { computed, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        activePath?: string;
    }>(),
    {},
);

const page = usePage();

// Année fiscale — source de vérité dans les shared props Inertia.
// Tant qu'une seule année de règles est codée (2024), la sélection reste
// mécaniquement bloquée : min = max = currentYear → flèches désactivées.
const currentYear = computed((): number => page.props.fiscal.currentYear);
const availableYears = computed((): number[] => page.props.fiscal.availableYears);
const minYear = computed((): number => Math.min(...availableYears.value));
const maxYear = computed((): number => Math.max(...availableYears.value));

// Le modèle interne permet à l'utilisateur de naviguer entre années
// disponibles sans recharger la page (si plusieurs années existent un jour).
// En MVP la valeur reste figée à currentYear.
const selectedYear = ref<number>(currentYear.value);
watch(currentYear, (v) => {
    selectedYear.value = v;
});

const isMobile = useMediaQuery('(max-width: 767px)');
const sidebarOpen = ref<boolean>(false);

watch(isMobile, (mobile) => {
    if (!mobile) sidebarOpen.value = false;
});
</script>

<template>
    <div class="min-h-screen bg-slate-50">
        <SidebarNav
            v-model:open="sidebarOpen"
            :active-path="activePath"
        />

        <div
            class="flex min-h-screen min-w-0 flex-col md:pl-16 wide:pl-60"
        >
            <TopBar
                v-model:year="selectedYear"
                :min-year="minYear"
                :max-year="maxYear"
                @toggle-sidebar="sidebarOpen = !sidebarOpen"
            />
            <main class="flex-1">
                <div class="mx-auto max-w-[1600px] px-4 py-6 md:px-8 md:py-8">
                    <slot :year="selectedYear" />
                </div>
            </main>
        </div>

        <ToastContainer />
    </div>
</template>
