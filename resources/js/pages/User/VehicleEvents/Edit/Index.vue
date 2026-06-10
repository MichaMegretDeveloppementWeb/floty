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
type VehicleEvent = App.Data.User.VehicleEvent.VehicleEventData;

const props = defineProps<{
    vehicle: VehicleHeader;
    vehicleEvent: VehicleEvent;
    busyDates: string[];
    /** Suggestions du catalogue de natures (bloc réducteur figé + autres, supprimables avec id). */
    natureSuggestions: { reductive: string[]; other: string[]; deletable: { id: number; label: string }[] };
}>();

const backUrl = vehiclesShowRoute.url({ vehicle: props.vehicle.id }, { query: { tab: 'events' } });
</script>

<template>
    <Head title="Modifier l'événement" />

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
                    Modifier l'événement
                </h1>
                <p class="text-sm text-slate-500">
                    Sur {{ vehicle.brand }} {{ vehicle.model }} ({{ vehicle.licensePlate }}).
                </p>
            </div>

            <VehicleEventForm
                :vehicle-id="vehicle.id"
                :editing="vehicleEvent"
                :busy-dates="busyDates"
                :nature-suggestions="natureSuggestions"
            />
        </div>
    </UserLayout>
</template>
