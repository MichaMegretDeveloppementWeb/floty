<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import Heatmap from '@/Components/Features/Planning/Heatmap/Heatmap.vue';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer/WeekDrawer.vue';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { useApi } from '@/Composables/Shared/useApi';
import { useFiscalYear } from '@/Composables/Shared/useFiscalYear';
import { week as planningWeekRoute } from '@/routes/user/planning';

type Vehicle = App.Data.User.Planning.PlanningHeatmapVehicleData;
type Company = App.Data.User.Company.CompanyOptionData;
type WeekData = App.Data.User.Planning.PlanningWeekData;

defineProps<{
    vehicles: Vehicle[];
    companies: Company[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();

const api = useApi();
const drawerOpen = ref(false);
const weekData = ref<WeekData | null>(null);
const loadingWeek = ref(false);

async function openWeek(payload: {
    vehicleId: number;
    week: number;
}): Promise<void> {
    loadingWeek.value = true;

    try {
        weekData.value = await api.get<WeekData>(planningWeekRoute.url(), {
            vehicleId: payload.vehicleId,
            week: payload.week,
        });
        drawerOpen.value = true;
    } catch {
        // Toast erreur déjà affiché par useApi
    } finally {
        loadingWeek.value = false;
    }
}

function closeDrawer(): void {
    drawerOpen.value = false;
}

function onAssignmentsCreated(): void {
    drawerOpen.value = false;
    // Rafraîchit la page pour recalculer densités + taxes annuelles.
    router.reload({ only: ['vehicles'] });
}
</script>

<template>
    <Head title="Vue d'ensemble" />

    <UserLayout>
        <div class="flex flex-col gap-6">
            <header>
                <p class="eyebrow mb-1">Planning</p>
                <h1
                    class="text-2xl font-semibold tracking-tight text-slate-900 md:text-3xl"
                >
                    Vue d'ensemble · {{ fiscalYear }}
                </h1>
                <p class="mt-1 text-base text-slate-600">
                    Densité d'utilisation de la flotte semaine par semaine.
                    Cliquez sur une cellule pour attribuer des jours et voir
                    l'impact fiscal en temps réel.
                </p>
            </header>

            <Heatmap
                :vehicles="vehicles"
                :fiscal-year="fiscalYear"
                @cell-click="openWeek"
            />
        </div>

        <WeekDrawer
            :open="drawerOpen"
            :week="weekData"
            :companies="companies"
            :fiscal-year="fiscalYear"
            @close="closeDrawer"
            @assignments-created="onAssignmentsCreated"
        />
    </UserLayout>
</template>
