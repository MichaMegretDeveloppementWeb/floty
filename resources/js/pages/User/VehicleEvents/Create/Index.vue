<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ChevronLeft } from 'lucide-vue-next';
import UserLayout from '@/Components/Layouts/UserLayout.vue';
import { show as vehiclesShowRoute } from '@/routes/user/vehicles';
import VehicleEventForm from '../partials/VehicleEventForm.vue';

type VehicleHeader = {
    id: number;
    licensePlate: string;
    brand: string;
    model: string;
};

const props = defineProps<{
    vehicle: VehicleHeader;
    busyDates: string[];
    /** ISO Y-m-d pré-sélectionnée (ajout depuis un jour précis de la timeline). */
    initialDate?: string | null;
    /** Suggestions du catalogue de natures (bloc réducteur figé + autres + customs supprimables). */
    natureSuggestions: { reductive: string[]; other: string[]; custom: { id: number; label: string }[] };
}>();

const backUrl = vehiclesShowRoute.url({ vehicle: props.vehicle.id }, { query: { tab: 'events' } });
</script>

<template>
    <Head title="Nouvel événement" />

    <UserLayout>
        <div class="m-auto flex w-full max-w-3xl flex-col gap-6">
            <div class="flex flex-col gap-2">
                <Link
                    :href="backUrl"
                    class="inline-flex w-fit items-center gap-1 text-xs font-medium text-slate-500 transition-colors hover:text-slate-900"
                >
                    <ChevronLeft :size="14" :stroke-width="1.75" />
                    {{ vehicle.licensePlate }} · Événements
                </Link>
                <h1 class="text-[28px] font-semibold leading-none tracking-tight text-slate-900">
                    Nouvel événement
                </h1>
                <p class="text-sm text-slate-500">
                    Enregistrez un événement sur {{ vehicle.brand }} {{ vehicle.model }} ({{ vehicle.licensePlate }}).
                </p>
            </div>

            <VehicleEventForm
                :vehicle-id="vehicle.id"
                :editing="null"
                :busy-dates="busyDates"
                :initial-date="initialDate"
                :nature-suggestions="natureSuggestions"
            />
        </div>
    </UserLayout>
</template>
