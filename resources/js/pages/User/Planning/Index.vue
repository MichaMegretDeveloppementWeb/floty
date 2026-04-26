<script setup lang="ts">
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import Heatmap from '@/Components/Features/Planning/Heatmap.vue';
import WeekDrawer from '@/Components/Features/Planning/WeekDrawer.vue';
import { useFiscalYear } from '@/composables/useFiscalYear';
import { getJson } from '@/lib/http';
import type { CompanyColor } from '@/types/ui';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

type Vehicle = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
    userType: string;
    energy: string;
    co2Method: string;
    co2Value: number | null;
    taxableHorsepower: number | null;
    weeks: number[];
    daysTotal: number;
    annualTaxDue: number;
};

type Company = {
    id: number;
    shortCode: string;
    legalName: string;
    color: CompanyColor;
};

type WeekData = {
    weekNumber: number;
    weekStart: string;
    weekEnd: string;
    vehicleId: number;
    licensePlate: string;
    days: Array<{
        date: string;
        dayLabel: string;
        assignment: {
            id: number;
            company: Company;
        } | null;
    }>;
    companiesOnWeek: Array<{
        company: Company;
        days: number;
    }>;
};

defineProps<{
    vehicles: Vehicle[];
    companies: Company[];
}>();

const { currentYear: fiscalYear } = useFiscalYear();

const drawerOpen = ref(false);
const weekData = ref<WeekData | null>(null);
const loadingWeek = ref(false);

async function openWeek(payload: {
    vehicleId: number;
    week: number;
}): Promise<void> {
    loadingWeek.value = true;
    try {
        weekData.value = await getJson<WeekData>('/app/planning/week', {
            vehicleId: payload.vehicleId,
            week: payload.week,
        });
        drawerOpen.value = true;
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
